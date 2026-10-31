<?php

require_once __DIR__.'/../Fees/helpers.php';

use App\Actions\Saas\CountCensusStudents;
use App\Actions\Saas\IssueSaasInvoice;
use App\Actions\Saas\NextSaasInvoiceNumber;
use App\Actions\Saas\RecordSaasPayment;
use App\Actions\Saas\RunSaasCensus;
use App\Actions\Saas\SavePlan;
use App\Actions\Saas\SetSchoolPlan;
use App\Actions\Saas\VoidSaasInvoice;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\MoveStudent;
use App\Actions\Students\WithdrawStudent;
use App\Enums\EnrolmentStatus;
use App\Enums\PlatformRoleKey;
use App\Enums\RoleKey;
use App\Enums\SaasInvoiceStatus;
use App\Enums\SchoolStatus;
use App\Enums\StudentStatus;
use App\Livewire\Console\Invoices;
use App\Livewire\Console\Plans;
use App\Livewire\Console\Schools\Billing;
use App\Models\Enrolment;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| SPEC section 8: Orange Tree bills each school per active student per term, counted on the
| term's census date (Greenhill's first term: 8 Sep 2026, census 22 Sep 2026; today 28 Oct).
*/

beforeEach(function () {
    setUpFeeSchool();
    $this->plan = Plan::factory()->create(['name' => 'Standard', 'rate_per_student' => 1500]); // GH₵15.00
    $this->school->forceFill(['plan_id' => $this->plan->id])->save();
    $this->superAdmin = User::factory()->platform()->create();
    $this->support = User::factory()->platform(PlatformRoleKey::Support)->create();
});

function saasPupil(string $first, string $date): Student
{
    $stream = test()->p1->streams()->sole();

    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Mensah', 'stream_id' => $stream->id, 'admitted_on' => $date]);
}

function saasCensus(): int
{
    return app(CountCensusStudents::class)->handle(test()->school, test()->term1->census_on);
}

describe('the census count', function () {
    it('counts students enrolled on the census date, once each', function () {
        saasPupil('Ama', '2026-09-08');
        saasPupil('Kofi', '2026-09-22');   // joined on census day: counts
        saasPupil('Esi', '2026-09-23');    // joined after: does not
        $moved = saasPupil('Yaw', '2026-09-08');
        app(MoveStudent::class)->handle($moved, $this->p2->streams()->sole(), on: '2026-09-22'); // moved that day: once

        expect(saasCensus())->toBe(3);
    });

    it('counts once a student whose old enrolment ends on census day as a new one starts', function () {
        // e.g. promoted that day: the old enrolment still counts on its last day, as does the new.
        $student = saasPupil('Ama', '2026-09-08');
        $old = $student->currentEnrolment()->sole();
        $old->forceFill(['ends_on' => '2026-09-22', 'status' => EnrolmentStatus::Promoted, 'is_current' => null])->save();
        Enrolment::factory()->create([
            'school_id' => $this->school->id, 'student_id' => $student->id, 'academic_year_id' => $old->academic_year_id,
            'stream_id' => $this->p2->streams()->sole()->id, 'starts_on' => '2026-09-22',
        ]);

        expect(saasCensus())->toBe(1);
    });

    it('counts a leaver on their last day but not after', function () {
        $left = saasPupil('Ama', '2026-09-08');
        $stays = saasPupil('Kofi', '2026-09-08');
        app(WithdrawStudent::class)->handle($left, StudentStatus::Withdrawn, '2026-09-15');
        $lastDay = saasPupil('Esi', '2026-09-08');
        app(WithdrawStudent::class)->handle($lastDay, StudentStatus::Withdrawn, '2026-09-22');

        expect(saasCensus())->toBe(2); // Kofi and Esi
    });

    it("never counts another school's students or deleted records", function () {
        saasPupil('Ama', '2026-09-08');
        saasPupil('Gone', '2026-09-08')->delete();
        $hillview = School::factory()->active()->create();
        app(CurrentSchool::class)->runFor($hillview, fn () => Enrolment::factory()->create(['school_id' => $hillview->id, 'starts_on' => '2026-09-01']));

        expect(saasCensus())->toBe(1);
    });
});

describe('issuing', function () {
    beforeEach(function () {
        foreach (range(1, 4) as $i) {
            saasPupil("Child{$i}", '2026-09-08');
        }
    });

    it('bills students × rate, copying the plan and dates', function () {
        $invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);

        expect($invoice->number)->toBe('OT/INV/2026/0001')
            ->and($invoice->student_count)->toBe(4)
            ->and($invoice->rate_per_student->pesewas)->toBe(1500)
            ->and($invoice->amount->pesewas)->toBe(6000)
            ->and($invoice->plan_name)->toBe('Standard')
            ->and($invoice->census_on->toDateString())->toBe('2026-09-22')
            ->and($invoice->due_on->toDateString())->toBe('2026-11-27')
            ->and($invoice->status())->toBe(SaasInvoiceStatus::Unpaid);
    });

    it('keeps the old rate on an invoice when the plan changes', function () {
        $invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);

        app(SavePlan::class)->handle($this->plan, ['name' => 'Standard', 'rate' => '20.00']);

        expect($invoice->fresh()->amount->pesewas)->toBe(6000)
            ->and($this->plan->fresh()->rate_per_student->pesewas)->toBe(2000);
    });

    it('bills a term only once', function () {
        app(IssueSaasInvoice::class)->handle($this->school, $this->term1);

        expect(fn () => app(IssueSaasInvoice::class)->handle($this->school, $this->term1))
            ->toThrow(ValidationException::class, 'already has an invoice');
    });

    it('can bill the term again after a void, with a new number', function () {
        $first = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);
        app(VoidSaasInvoice::class)->handle($this->superAdmin, $first, 'Wrong count');

        $second = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);

        expect($second->number)->toBe('OT/INV/2026/0002')
            ->and(SaasInvoice::live()->count())->toBe(1);
    });

    it('does not bill trial or suspended schools, or schools with no plan', function (Closure $change, string $message) {
        $change($this->school);

        expect(fn () => app(IssueSaasInvoice::class)->handle($this->school->fresh(), $this->term1))
            ->toThrow(ValidationException::class, $message);
    })->with([
        'trial' => [fn (School $s) => $s->forceFill(['status' => SchoolStatus::Trial])->save(), 'not billed while it is'],
        'suspended' => [fn (School $s) => $s->forceFill(['status' => SchoolStatus::Suspended])->save(), 'not billed while it is'],
        'no plan' => [fn (School $s) => $s->forceFill(['plan_id' => null])->save(), 'Choose a plan'],
    ]);

    it('waits for the census date', function () {
        expect(fn () => app(IssueSaasInvoice::class)->handle($this->school, $this->term2))
            ->toThrow(ValidationException::class, 'has not come yet');
    });

    it('numbers invoices in one series across schools', function () {
        $sequence = app(NextSaasInvoiceNumber::class);

        expect([$sequence->handle(2026), $sequence->handle(2026), $sequence->handle(2027)])
            ->toBe(['OT/INV/2026/0001', 'OT/INV/2026/0002', 'OT/INV/2027/0001']);
    });

    it('logs the invoice', function () {
        $invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);

        expect(Activity::where('log_name', 'saas')->where('subject_type', $invoice->getMorphClass())->where('subject_id', $invoice->id)->exists())->toBeTrue();
    });
});

describe('the daily census command', function () {
    beforeEach(function () {
        saasPupil('Ama', '2026-09-08');
        saasPupil('Kofi', '2026-09-08');
    });

    it("bills a school whose current term's census has passed, once", function () {
        $this->artisan('saas:census')->assertSuccessful()->expectsOutputToContain('issued OT/INV/2026/0001');
        $this->artisan('saas:census')->assertSuccessful()->expectsOutputToContain('already invoiced');

        expect(SaasInvoice::sole()->amount->pesewas)->toBe(3000);
    });

    it('issues nothing on a dry run', function () {
        $this->artisan('saas:census', ['--school' => 'greenhill', '--dry-run' => true])
            ->assertSuccessful()->expectsOutputToContain('dry run: not issued');

        expect(SaasInvoice::count())->toBe(0);
    });

    it('counts trial schools but does not bill them', function () {
        $this->school->forceFill(['status' => SchoolStatus::Trial])->save();

        $rows = app(RunSaasCensus::class)->handle();

        expect($rows[0])->toMatchArray(['students' => 2, 'result' => 'on trial: not billed'])
            ->and(SaasInvoice::count())->toBe(0);
    });

    it('waits while the census date is still ahead', function () {
        $this->travelTo('2026-09-21 09:00:00');

        expect(app(RunSaasCensus::class)->handle()[0]['result'])->toBe('census date not reached');
    });

    it('rejects an unknown school', function () {
        $this->artisan('saas:census', ['--school' => 'nowhere'])->assertFailed();
    });

    it('is scheduled daily', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'saas:census'));

        expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 1 * * *');
    });
});

describe('payments and voids', function () {
    beforeEach(function () {
        foreach (range(1, 4) as $i) {
            saasPupil("Child{$i}", '2026-09-08');
        }
        $this->invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1); // GH₵60.00
    });

    function saasPay(array $input = []): SaasPayment
    {
        return app(RecordSaasPayment::class)->handle(test()->superAdmin, test()->invoice, array_merge(
            ['amount' => '25.00', 'paid_on' => '2026-10-28', 'method' => 'bank_transfer', 'reference' => 'GCB 123'], $input,
        ));
    }

    it('takes part payments and is paid once covered', function () {
        saasPay();
        expect($this->invoice->fresh()->balance()->pesewas)->toBe(3500)
            ->and($this->invoice->fresh()->status())->toBe(SaasInvoiceStatus::Unpaid);

        saasPay(['amount' => '35.00']);
        expect($this->invoice->fresh()->status())->toBe(SaasInvoiceStatus::Paid);
    });

    it('refuses more than the balance, a future date, or a void invoice', function () {
        expect(fn () => saasPay(['amount' => '60.01']))->toThrow(ValidationException::class, 'more than the balance')
            ->and(fn () => saasPay(['paid_on' => '2026-10-29']))->toThrow(ValidationException::class, 'cannot be in the future');

        app(VoidSaasInvoice::class)->handle($this->superAdmin, $this->invoice, 'Mistake');
        expect(fn () => saasPay())->toThrow(ValidationException::class, 'voided');
    });

    it('never changes or deletes a payment, and logs it', function () {
        $payment = saasPay();

        expect(fn () => $payment->forceFill(['amount' => 1])->save())->toThrow(LogicException::class)
            ->and(fn () => $payment->delete())->toThrow(LogicException::class)
            ->and(Activity::where('log_name', 'saas')->where('subject_type', $payment->getMorphClass())->exists())->toBeTrue();
    });

    it('becomes overdue after the due date', function () {
        $this->travelTo('2026-11-28 09:00:00');

        expect($this->invoice->fresh()->status())->toBe(SaasInvoiceStatus::Overdue)
            ->and(SaasInvoice::whereStatus(SaasInvoiceStatus::Overdue)->count())->toBe(1)
            ->and(SaasInvoice::whereStatus(SaasInvoiceStatus::Unpaid)->count())->toBe(0);
    });

    it('needs a reason to void, and cannot void once paid into', function () {
        expect(fn () => app(VoidSaasInvoice::class)->handle($this->superAdmin, $this->invoice, ' '))->toThrow(ValidationException::class, 'Say why');

        saasPay();
        expect(fn () => app(VoidSaasInvoice::class)->handle($this->superAdmin, $this->invoice->fresh(), 'Oops'))->toThrow(ValidationException::class, 'cannot be voided');
    });

    it('filters by status in SQL the same way as per invoice', function () {
        saasPay(['amount' => '60.00']);

        expect(SaasInvoice::whereStatus(SaasInvoiceStatus::Paid)->count())->toBe(1)
            ->and(SaasInvoice::whereStatus(SaasInvoiceStatus::Unpaid)->count())->toBe(0);
    });
});

describe('the console', function () {
    beforeEach(function () {
        saasPupil('Ama', '2026-09-08');
        $this->head = staffWith(RoleKey::Headteacher);
        app(CurrentSchool::class)->clear(); // the console has no current school
    });

    it('lets a super admin choose the plan and issue the invoice from the school page', function () {
        app(CurrentSchool::class)->clear();
        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(Billing::class, ['school' => $this->school])
            ->assertSee('1 active students')
            ->call('issue')
            ->assertHasNoErrors()
            ->assertSee('OT/INV/2026/0001')
            ->assertSee('GH₵15.00');
    });

    it('records a payment from the school page', function () {
        app(CurrentSchool::class)->clear();
        $invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);
        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(Billing::class, ['school' => $this->school])
            ->call('startPayment', $invoice->id)
            ->assertSet('payment.amount', '15.00')
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertSee('Paid');
    });

    it('lets support staff look but not change anything', function () {
        app(CurrentSchool::class)->clear();
        $invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);
        $this->actingAs($this->support, 'platform');

        Livewire::test(Billing::class, ['school' => $this->school])->assertOk()->assertDontSee('Record payment')
            ->call('startPayment', $invoice->id)->assertForbidden();
        Livewire::test(Plans::class)->call('startCreate')->assertForbidden();
        $this->get(route('console.invoices.pdf', $invoice))->assertOk();
    });

    it('creates and renames plans', function () {
        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(Plans::class)
            ->call('startCreate')
            ->set('form.name', 'Basic')->set('form.rate', '10')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('GH₵10.00');

        expect(Plan::where('name', 'Basic')->sole()->rate_per_student->pesewas)->toBe(1000);
    });

    it('only offers plans in use when choosing one', function () {
        $retired = Plan::factory()->create(['is_active' => false]);

        expect(fn () => app(SetSchoolPlan::class)->handle($this->school, $retired->id))->toThrow(ValidationException::class);
    });

    it('shows what schools owe and what is overdue', function () {
        app(CurrentSchool::class)->clear();
        app(IssueSaasInvoice::class)->handle($this->school, $this->term1);
        $this->travelTo('2026-12-01 09:00:00');
        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(Invoices::class)
            ->assertSee('OT/INV/2026/0001')
            ->assertSeeInOrder(['Owed by schools', 'GH₵15.00', 'Of which overdue', 'GH₵15.00'])
            ->set('statusFilter', 'paid')
            ->assertDontSee('OT/INV/2026/0001');
    });

    it('renders the invoice PDF', function () {
        app(CurrentSchool::class)->clear();
        $invoice = app(IssueSaasInvoice::class)->handle($this->school, $this->term1);

        $response = $this->actingAs($this->superAdmin, 'platform')->get(route('console.invoices.pdf', $invoice));

        $response->assertOk();
        expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    });

    it('keeps school staff out', function () {
        $this->actingAs($this->head)->get('http://admin.localhost/invoices')->assertRedirect();
    });
});
