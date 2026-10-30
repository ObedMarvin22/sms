<?php

use App\Actions\Fees\DeleteInstalmentPlan;
use App\Actions\Fees\RemoveScholarship;
use App\Actions\Fees\SaveDiscount;
use App\Actions\Fees\SaveInstalmentPlan;
use App\Actions\Fees\SaveScholarship;
use App\Actions\Fees\SetInstalmentPlan;
use App\Actions\Fees\SetStudentDiscount;
use App\Enums\DiscountKind;
use App\Enums\DiscountType;
use App\Enums\RoleKey;
use App\Livewire\Fees\Discounts;
use App\Livewire\Fees\InstalmentPlans;
use App\Livewire\Students\Fees;
use App\Models\Activity;
use App\Models\Discount;
use App\Models\InstalmentPlan;
use App\Models\InstalmentPlanStep;
use App\Models\StudentDiscount;
use App\Models\StudentInstalmentPlan;
use App\Support\Money\Percent;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/helpers.php';

beforeEach(fn () => setUpFeeSchool());

/**
 * @param  array<string, mixed>  $input
 */
function discount(array $input = [], ?Discount $existing = null): Discount
{
    return app(SaveDiscount::class)->handle($existing, array_merge(
        ['name' => 'Staff child', 'kind' => 'staff_child', 'type' => 'percent', 'value' => '50', 'whole_bill' => true],
        $input,
    ));
}

/**
 * @param  list<array{string, string}>  $steps  [due_on, percent]
 */
function plan(string $name, array $steps, bool $default = false, ?InstalmentPlan $existing = null): InstalmentPlan
{
    return app(SaveInstalmentPlan::class)->handle(test()->term1, $existing, $name,
        array_map(fn (array $s) => ['due_on' => $s[0], 'percent' => $s[1]], $steps), $default);
}

it('reads percentages as basis points', function () {
    expect(Percent::parse('12.5'))->toBe(1_250)
        ->and(Percent::parse('100%'))->toBe(10_000)
        ->and(Percent::format(3_333))->toBe('33.33%')
        ->and(Percent::format(5_000))->toBe('50%')
        ->and(fn () => Percent::parse('12.345'))->toThrow(InvalidArgumentException::class);
});

describe('discounts', function () {
    it('saves a percentage on chosen items or a fixed amount, and describes it', function () {
        $half = discount(['whole_bill' => false, 'fee_item_ids' => [(string) $this->tuition->id]]);
        $fixed = discount(['name' => 'Bursary', 'kind' => 'custom', 'type' => 'fixed', 'value' => '200']);
        $names = [$this->tuition->id => 'Tuition'];

        expect($half->value)->toBe(5_000)
            ->and($half->fee_item_ids)->toBe([$this->tuition->id])
            ->and($half->describe($names))->toBe('50% of Tuition')
            ->and($fixed->value)->toBe(20_000)
            ->and($fixed->describe($names))->toBe('GH₵200.00 off the bill');
    });

    it('refuses impossible discounts', function (array $input, string $field) {
        try {
            discount($input);
            $this->fail('saved');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }
    })->with([
        'over 100%' => [['value' => '100.01'], 'value'],
        'zero' => [['value' => '0'], 'value'],
        'not a number' => [['value' => 'half'], 'value'],
        'bad fixed amount' => [['type' => 'fixed', 'value' => '-20'], 'value'],
        'no items chosen' => [['whole_bill' => false, 'fee_item_ids' => []], 'fee_item_ids'],
        'unknown item' => [['whole_bill' => false, 'fee_item_ids' => ['999999']], 'fee_item_ids'],
        'no kind' => [['kind' => 'friends'], 'kind'],
    ]);

    it('allows only one sibling discount in use', function () {
        discount(['name' => 'Second child', 'kind' => 'sibling', 'value' => '10']);

        expect(fn () => discount(['name' => 'Third child', 'kind' => 'sibling', 'value' => '20']))
            ->toThrow(ValidationException::class, 'already a sibling discount');
    });

    it('gives staff-child and custom discounts by hand, never the sibling one, logging each', function () {
        $ama = feeStudent();
        $staffChild = discount();
        $sibling = discount(['name' => 'Second child', 'kind' => 'sibling', 'value' => '10']);

        app(SetStudentDiscount::class)->handle($ama, $staffChild, true);
        app(SetStudentDiscount::class)->handle($ama, $staffChild, true);
        expect(StudentDiscount::where('student_id', $ama->id)->count())->toBe(1)
            ->and(fn () => app(SetStudentDiscount::class)->handle($ama, $sibling, true))->toThrow(ValidationException::class, 'automatically');

        app(SetStudentDiscount::class)->handle($ama, $staffChild, false);
        expect(StudentDiscount::count())->toBe(0)
            ->and(Activity::where('subject_type', (new StudentDiscount)->getMorphClass())->pluck('event')->all())->toBe(['created', 'deleted']);
    });

    it('will not turn a hand-given discount into the sibling discount', function () {
        $custom = discount(['name' => 'Church members', 'kind' => 'custom']);
        app(SetStudentDiscount::class)->handle(feeStudent(), $custom, true);

        expect(fn () => discount(['name' => 'Church members', 'kind' => 'sibling'], $custom))->toThrow(ValidationException::class);
    });

    it('logs a change to a discount\'s size', function () {
        $staffChild = discount();
        discount(['value' => '40'], $staffChild);

        $entry = Activity::where('log_name', 'fees')->where('subject_id', $staffChild->id)->where('event', 'updated')->sole();
        expect($entry->attribute_changes['old']['value'])->toBe(5_000)
            ->and($entry->attribute_changes['attributes']['value'])->toBe(4_000);
    });
});

describe('scholarships', function () {
    it('gives a student a scholarship with a sponsor and an end term, then removes it', function () {
        $ama = feeStudent();
        $scholarship = app(SaveScholarship::class)->handle($ama, null, [
            'name' => 'Church scholarship', 'sponsor' => 'Christ the King Parish', 'type' => 'percent', 'value' => '100',
            'whole_bill' => false, 'fee_item_ids' => [$this->tuition->id], 'ends_term_id' => $this->term2->id,
        ]);

        expect($scholarship->isScholarship())->toBeTrue()
            ->and($scholarship->value)->toBe(10_000)
            ->and($scholarship->describe([$this->tuition->id => 'Tuition']))->toBe('100% of Tuition')
            ->and($scholarship->ends_term_id)->toBe($this->term2->id);

        app(RemoveScholarship::class)->handle($scholarship);
        expect(StudentDiscount::count())->toBe(0)
            ->and(Activity::where('subject_type', (new StudentDiscount)->getMorphClass())->where('event', 'deleted')->exists())->toBeTrue();
    });

    it('will not remove a school discount as if it were a scholarship', function () {
        $ama = feeStudent();
        app(SetStudentDiscount::class)->handle($ama, discount(), true);

        expect(fn () => app(RemoveScholarship::class)->handle(StudentDiscount::sole()))->toThrow(ValidationException::class);
    });
});

describe('instalment plans', function () {
    it('saves steps in order that add up to 100%', function () {
        $plan = plan('Two instalments', [['2026-09-18', '50'], ['2026-10-30', '50']], default: true);

        expect($plan->is_default)->toBeTrue()
            ->and($plan->steps->map(fn ($s) => [$s->due_on->toDateString(), $s->percent])->all())
            ->toBe([['2026-09-18', 5_000], ['2026-10-30', 5_000]]);
    });

    it('refuses plans that do not add up or are out of order', function (array $steps, string $field) {
        try {
            plan('Bad plan', $steps);
            $this->fail('saved');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field);
        }
    })->with([
        'under 100%' => [[['2026-09-18', '50'], ['2026-10-30', '40']], 'steps'],
        'over 100%' => [[['2026-09-18', '60'], ['2026-10-30', '50']], 'steps'],
        'dates out of order' => [[['2026-10-30', '50'], ['2026-09-18', '50']], 'steps.1.due_on'],
        'same date twice' => [[['2026-10-30', '50'], ['2026-10-30', '50']], 'steps.1.due_on'],
        'outside the term' => [[['2027-02-01', '100']], 'steps.0.due_on'],
        'no date' => [[['', '100']], 'steps.0.due_on'],
        'no steps' => [[], 'steps'],
        'seven steps' => [array_map(fn (int $d) => [sprintf('2026-10-%02d', $d), '10'], range(1, 7)), 'steps'],
    ]);

    it('keeps one default per term, logging the switch', function () {
        $first = plan('Full payment', [['2026-09-25', '100']], default: true);
        $second = plan('Two instalments', [['2026-09-18', '50'], ['2026-10-30', '50']], default: true);

        expect($first->fresh()->is_default)->toBeFalse()
            ->and($second->fresh()->is_default)->toBeTrue()
            ->and(Activity::where('subject_id', $first->id)->where('subject_type', (new InstalmentPlan)->getMorphClass())->where('event', 'updated')->exists())->toBeTrue();
    });

    it('rewrites steps when a plan changes', function () {
        $plan = plan('Three instalments', [['2026-09-18', '40'], ['2026-10-02', '30'], ['2026-10-30', '30']]);
        plan('Three instalments', [['2026-09-18', '50'], ['2026-10-30', '50']], existing: $plan);

        expect(InstalmentPlanStep::where('instalment_plan_id', $plan->id)->count())->toBe(2);
    });

    it('puts a student on a plan for the term, or back on the default', function () {
        $ama = feeStudent();
        $plan = plan('Two instalments', [['2026-09-18', '50'], ['2026-10-30', '50']]);

        app(SetInstalmentPlan::class)->handle($ama, $this->term1, $plan);
        expect(StudentInstalmentPlan::sole()->instalment_plan_id)->toBe($plan->id)
            ->and(fn () => app(SetInstalmentPlan::class)->handle($ama, $this->term2, $plan))->toThrow(ValidationException::class)
            ->and(fn () => app(DeleteInstalmentPlan::class)->handle($plan))->toThrow(ValidationException::class, '1 student follows');

        app(SetInstalmentPlan::class)->handle($ama, $this->term1, null);
        app(DeleteInstalmentPlan::class)->handle($plan->load('steps'));
        expect(InstalmentPlan::count())->toBe(0)->and(InstalmentPlanStep::count())->toBe(0);
    });
});

describe('screens', function () {
    beforeEach(function () {
        $this->bursar = staffWith(RoleKey::Bursar);
        $this->head = staffWith(RoleKey::Headteacher);
        $this->onSchool($this->school);
    });

    it('creates a discount on the discounts screen', function () {
        Livewire::actingAs($this->bursar)->test(Discounts::class)
            ->call('startCreate')
            ->set('form.name', 'Staff child')
            ->set('form.kind', 'staff_child')
            ->set('form.value', '50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('50% of the bill');

        expect(Discount::sole()->type)->toBe(DiscountType::Percent)->and(Discount::sole()->kind)->toBe(DiscountKind::StaffChild);
    });

    it('builds a plan with added and removed steps', function () {
        Livewire::actingAs($this->bursar)->test(InstalmentPlans::class)
            ->assertSee('every bill is due in full')
            ->call('startCreate')
            ->set('name', 'Three instalments')
            ->call('addStep')
            ->set('steps.0.due_on', '2026-09-18')->set('steps.0.percent', '50')
            ->set('steps.1.due_on', '2026-10-02')->set('steps.1.percent', '25')
            ->set('steps.2.due_on', '2026-10-30')->set('steps.2.percent', '25')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('50% by 18 Sep · 25% by 2 Oct · 25% by 30 Oct');

        expect(InstalmentPlan::sole()->is_default)->toBeTrue(); // the first plan of a term starts as default
    });

    it('shows the total when the steps do not add up', function () {
        Livewire::actingAs($this->bursar)->test(InstalmentPlans::class)
            ->call('startCreate')
            ->set('name', 'Bad')
            ->set('steps.0.due_on', '2026-09-18')
            ->set('steps.1.due_on', '2026-10-30')->set('steps.1.percent', '40')
            ->call('save')
            ->assertHasErrors('steps')
            ->assertSee('add up to 90%');
    });

    it('gives discounts, scholarships and a plan on the student page', function () {
        $ama = feeStudent();
        $staffChild = discount();
        $plan = plan('Two instalments', [['2026-09-18', '50'], ['2026-10-30', '50']]);

        Livewire::actingAs($this->bursar)->test(Fees::class, ['studentId' => $ama->id])
            ->call('toggleDiscount', $staffChild->id)
            ->call('startScholarship')
            ->set('form.name', 'Old students\' scholarship')
            ->set('form.type', 'fixed')
            ->set('form.value', '300')
            ->call('saveScholarship')
            ->assertHasNoErrors()
            ->assertSee('GH₵300.00 off the bill')
            ->set('planId', (string) $plan->id)
            ->assertHasNoErrors();

        expect(StudentDiscount::where('student_id', $ama->id)->count())->toBe(2)
            ->and(StudentInstalmentPlan::sole()->instalment_plan_id)->toBe($plan->id);
    });

    it('lets the headteacher see discounts and plans but change nothing', function () {
        $ama = feeStudent();
        $staffChild = discount();
        plan('Two instalments', [['2026-09-18', '50'], ['2026-10-30', '50']]);

        Livewire::actingAs($this->head)->test(Discounts::class)->assertSee('Staff child')->call('startCreate')->assertForbidden();
        Livewire::actingAs($this->head)->test(InstalmentPlans::class)->assertSee('Two instalments')->call('startCreate')->assertForbidden();
        Livewire::actingAs($this->head)->test(Fees::class, ['studentId' => $ama->id])->call('toggleDiscount', $staffChild->id)->assertForbidden();
        Livewire::actingAs($this->head)->test(Fees::class, ['studentId' => $ama->id])->call('startScholarship')->assertForbidden();
    });
});
