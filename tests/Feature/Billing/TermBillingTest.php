<?php

use App\Actions\Billing\BillRunStudents;
use App\Actions\Billing\IssueBill;
use App\Actions\Billing\StartTermBilling;
use App\Actions\Billing\StudentNotBillable;
use App\Actions\Fees\SaveDiscount;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Fees\SaveInstalmentPlan;
use App\Actions\Fees\SaveScholarship;
use App\Actions\Fees\SetFeeOption;
use App\Actions\Fees\SetInstalmentPlan;
use App\Actions\Fees\SetOffersBoarding;
use App\Actions\Fees\SetResidence;
use App\Actions\Fees\SetStudentDiscount;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Guardians\LinkGuardian;
use App\Actions\Students\AdmitStudent;
use App\Actions\Students\WithdrawStudent;
use App\Enums\BillingRunStatus;
use App\Enums\BillLineKind;
use App\Enums\LedgerEntryType;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Enums\StudentStatus;
use App\Jobs\BillStudentsChunk;
use App\Livewire\Fees\Billing;
use App\Livewire\Students\Fees;
use App\Models\Activity;
use App\Models\Bill;
use App\Models\BillingRun;
use App\Models\FeeItem;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Fees/helpers.php';

beforeEach(function () {
    setUpFeeSchool();
    // Primary 1, first term: Tuition GH₵1,000, PTA dues GH₵100, Bus GH₵200 (optional).
    fees($this->p1, ['Tuition' => '1000', 'PTA dues' => '100', 'Bus' => '200']);
});

/**
 * @param  array<string, string>  $amounts  fee item name => amount
 */
function fees(SchoolClass $class, array $amounts, ?Term $term = null, Residence $residence = Residence::Day): void
{
    $byId = [];
    foreach ($amounts as $name => $amount) {
        $byId[FeeItem::where('name', $name)->sole()->id] = $amount;
    }
    app(SaveFeeStructure::class)->handle($term ?? test()->term1, $class, $residence, $byId);
}

function bill(Student $student, ?Term $term = null): Bill
{
    return app(IssueBill::class)->handle($student, $term ?? test()->term1)->load(['lines', 'instalments']);
}

/**
 * @return list<array{string, int}> [description, pesewas]
 */
function linesOf(Bill $bill): array
{
    return $bill->lines->map(fn ($l) => [$l->description, $l->amount->pesewas])->values()->all();
}

function pupilIn(SchoolClass $class, string $first, string $admitted = '2026-09-08'): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $first, 'last_name' => 'Mensah', 'stream_id' => $class->streams()->sole()->id, 'admitted_on' => $admitted]);
}

describe('working out a bill', function () {
    it('charges the compulsory items, and optional ones only to students who opted in', function () {
        $ama = feeStudent('Ama');
        $kofi = feeStudent('Kofi');
        app(SetFeeOption::class)->handle($kofi, $this->bus, true);

        expect(linesOf(bill($ama)))->toBe([['Tuition', 100_000], ['PTA dues', 10_000]])
            ->and(linesOf(bill($kofi)))->toBe([['Tuition', 100_000], ['PTA dues', 10_000], ['Bus', 20_000]]);
    });

    it('records the bill once in the ledger and is due in full on the first day of term by default', function () {
        $bill = bill(feeStudent());

        $entry = LedgerEntry::sole();
        expect($bill->net->format())->toBe('GH₵1,100.00')
            ->and($entry->type)->toBe(LedgerEntryType::Bill)
            ->and($entry->amount->pesewas)->toBe(110_000)
            ->and($entry->bill_id)->toBe($bill->id)
            ->and($bill->instalments->map(fn ($i) => [$i->due_on->toDateString(), $i->amount->pesewas])->all())->toBe([['2026-09-08', 110_000]]);
    });

    it('works every reduction out on the full price and shows each as its own line', function () {
        $ama = feeStudent();
        $half = app(SaveDiscount::class)->handle(null, ['name' => 'Staff child', 'kind' => 'staff_child', 'type' => 'percent', 'value' => '50',
            'whole_bill' => false, 'fee_item_ids' => [$this->tuition->id]]);
        app(SetStudentDiscount::class)->handle($ama, $half, true);
        app(SaveScholarship::class)->handle($ama, null, ['name' => 'PTA award', 'sponsor' => 'PTA', 'type' => 'fixed', 'value' => '300']);

        $bill = bill($ama);

        // Tuition 1,000 + PTA 100; staff child 50% of tuition = 500; scholarship GH₵300 off the bill.
        expect(linesOf($bill))->toBe([['Tuition', 100_000], ['PTA dues', 10_000], ['Staff child', -50_000], ['PTA award (PTA)', -30_000]])
            ->and($bill->lines->pluck('kind')->all())->toBe([BillLineKind::Fee, BillLineKind::Fee, BillLineKind::Discount, BillLineKind::Scholarship])
            ->and([$bill->gross->pesewas, $bill->reductions->pesewas, $bill->net->pesewas])->toBe([110_000, 80_000, 30_000]);
    });

    it('never takes an item below zero, so a bill can reach zero but never go negative', function () {
        $ama = feeStudent();
        app(SaveScholarship::class)->handle($ama, null, ['name' => 'Full scholarship', 'type' => 'percent', 'value' => '100']);
        app(SetStudentDiscount::class)->handle($ama, app(SaveDiscount::class)->handle(null, ['name' => 'Staff child', 'kind' => 'staff_child', 'type' => 'fixed', 'value' => '5000']), true);

        $bill = bill($ama);

        // The staff-child discount comes first and takes everything; the scholarship has nothing left.
        expect($bill->net->pesewas)->toBe(0)
            ->and(linesOf($bill))->toBe([['Tuition', 100_000], ['PTA dues', 10_000], ['Staff child', -110_000]])
            ->and(LedgerEntry::count())->toBe(0);
    });

    it('rounds percentages to the pesewa, half up', function () {
        fees($this->p2, ['Tuition' => '333.33']);
        $kojo = pupilIn($this->p2, 'Kojo');
        app(SaveScholarship::class)->handle($kojo, null, ['name' => 'Eighth', 'type' => 'percent', 'value' => '12.5']);

        // 12.5% of 33,333 pesewas = 4,166.625 -> 4,167.
        expect(linesOf(bill($kojo))[1])->toBe(['Eighth', -4_167]);
    });

    it('splits the bill by the instalment plan, the last part taking the rounding', function () {
        fees($this->p2, ['Tuition' => '100.01']);
        $kojo = pupilIn($this->p2, 'Kojo');
        $plan = app(SaveInstalmentPlan::class)->handle($this->term1, null, 'Thirds',
            [['due_on' => '2026-09-18', 'percent' => '33.33'], ['due_on' => '2026-10-16', 'percent' => '33.33'], ['due_on' => '2026-11-13', 'percent' => '33.34']], false);
        app(SetInstalmentPlan::class)->handle($kojo, $this->term1, $plan);

        $instalments = bill($kojo)->instalments->map(fn ($i) => [$i->due_on->toDateString(), $i->amount->pesewas])->all();

        expect($instalments)->toBe([['2026-09-18', 3_333], ['2026-10-16', 3_333], ['2026-11-13', 3_335]])
            ->and(array_sum(array_column($instalments, 1)))->toBe(10_001);
    });

    it('uses the term\'s default plan for students without one', function () {
        app(SaveInstalmentPlan::class)->handle($this->term1, null, 'Halves',
            [['due_on' => '2026-09-18', 'percent' => '50'], ['due_on' => '2026-10-30', 'percent' => '50']], true);

        expect(bill(feeStudent())->instalments->map(fn ($i) => $i->amount->pesewas)->all())->toBe([55_000, 55_000]);
    });

    it('gives the sibling discount to the 2nd and later children who share a guardian', function () {
        app(SaveDiscount::class)->handle(null, ['name' => 'Second child', 'kind' => 'sibling', 'type' => 'percent', 'value' => '10']);
        $elder = pupilIn($this->p1, 'Esi');
        $younger = pupilIn($this->p1, 'Ebo', '2026-09-15');
        $other = pupilIn($this->p1, 'Yaw');
        $mother = app(AddGuardian::class)->handle($elder, ['name' => 'Akosua Mensah', 'phone' => '0244000001', 'relationship' => 'mother']);
        app(LinkGuardian::class)->handle($younger, $mother, ['relationship' => 'mother']);

        expect(bill($elder)->net->pesewas)->toBe(110_000)
            ->and(linesOf(bill($younger))[2])->toBe(['Second child', -11_000])
            ->and(bill($other)->net->pesewas)->toBe(110_000);
    });

    it('does not count a brother or sister who has left', function () {
        app(SaveDiscount::class)->handle(null, ['name' => 'Second child', 'kind' => 'sibling', 'type' => 'percent', 'value' => '10']);
        $elder = pupilIn($this->p1, 'Esi');
        $younger = pupilIn($this->p1, 'Ebo', '2026-09-15');
        $mother = app(AddGuardian::class)->handle($elder, ['name' => 'Akosua Mensah', 'phone' => '0244000001', 'relationship' => 'mother']);
        app(LinkGuardian::class)->handle($younger, $mother, ['relationship' => 'mother']);
        app(WithdrawStudent::class)->handle($elder, StudentStatus::Withdrawn, '2026-10-01');

        expect(bill($younger)->net->pesewas)->toBe(110_000);
    });

    it('stops a scholarship after its last term', function () {
        fees($this->p1, ['Tuition' => '1000'], $this->term2);
        $ama = feeStudent();
        app(SaveScholarship::class)->handle($ama, null, ['name' => 'One term', 'type' => 'percent', 'value' => '50', 'ends_term_id' => $this->term1->id]);

        expect(bill($ama)->reductions->pesewas)->toBe(55_000)
            ->and(bill($ama, $this->term2)->reductions->pesewas)->toBe(0);
    });

    it('shows last term\'s unpaid balance as arrears without charging it again', function () {
        fees($this->p1, ['Tuition' => '1000'], $this->term2);
        $ama = feeStudent();
        bill($ama);

        $second = bill($ama, $this->term2);

        expect($second->arrears->format())->toBe('GH₵1,100.00')
            ->and($second->net->format())->toBe('GH₵1,000.00')
            ->and($second->payable()->format())->toBe('GH₵2,100.00')
            ->and(LedgerEntry::balanceFor($ama->id)->format())->toBe('GH₵2,100.00');
    });

    it('bills boarders from the boarding structure', function () {
        app(SetOffersBoarding::class)->handle($this->school, true);
        fees($this->p1, ['Tuition' => '1000', 'PTA dues' => '100'], residence: Residence::Boarding);
        $kofi = feeStudent('Kofi');
        app(SetResidence::class)->handle($kofi, Residence::Boarding);
        fees($this->p1, ['Tuition' => '2500'], residence: Residence::Boarding);

        $bill = bill($kofi);
        expect($bill->residence)->toBe(Residence::Boarding)->and($bill->net->format())->toBe('GH₵2,500.00');
    });

    it('refuses students it cannot bill, saying why', function () {
        $kojo = pupilIn($this->p2, 'Kojo');

        expect(fn () => bill($kojo))->toThrow(StudentNotBillable::class, 'No Primary 2 (Day) fee structure for First term.')
            ->and(Bill::count())->toBe(0);
    });

    it('never bills a student twice for a term', function () {
        $ama = feeStudent();
        $first = bill($ama);
        $again = app(IssueBill::class)->handle($ama, $this->term1);

        expect($again->is($first))->toBeTrue()
            ->and($again->wasRecentlyCreated)->toBeFalse()
            ->and(Bill::count())->toBe(1)
            ->and(LedgerEntry::count())->toBe(1);
    });
});

describe('the ledger', function () {
    it('is append-only', function () {
        bill(feeStudent());
        $entry = LedgerEntry::sole();

        expect(fn () => $entry->forceFill(['amount' => Money::zero()])->save())->toThrow(LogicException::class, 'never changed')
            ->and(fn () => $entry->delete())->toThrow(LogicException::class, 'never deleted')
            ->and(LedgerEntry::sole()->amount->pesewas)->toBe(110_000);
    });
});

describe('billing runs', function () {
    beforeEach(function () {
        $this->bursar = staffWith(RoleKey::Bursar);
    });

    it('queues the students in chunks', function () {
        Queue::fake();
        foreach (range(1, 3) as $n) {
            feeStudent("Pupil{$n}");
        }

        $run = app(StartTermBilling::class)->handle($this->bursar, $this->term1);

        expect($run->total_students)->toBe(3);
        Queue::assertPushed(BillStudentsChunk::class, fn (BillStudentsChunk $job) => $job->schoolId() === $this->school->id && $job->studentIds !== []);
    });

    it('bills everyone, lists who was skipped and why, and logs the run', function () {
        $ama = feeStudent('Ama');
        feeStudent('Kofi');
        pupilIn($this->p2, 'Kojo'); // Primary 2 has no structure

        $run = app(StartTermBilling::class)->handle($this->bursar, $this->term1)->fresh();

        expect($run->status)->toBe(BillingRunStatus::Completed)
            ->and($run->billedCount())->toBe(2)
            ->and(array_values($run->skipped ?? []))->toBe([['name' => 'Kojo Mensah', 'reason' => 'No Primary 2 (Day) fee structure for First term.']])
            ->and($run->total_net->format())->toBe('GH₵2,200.00')
            ->and(Bill::where('student_id', $ama->id)->sole()->billing_run_id)->toBe($run->id)
            ->and(Activity::where('log_name', 'fees')->where('event', 'billing_finished')->sole()->properties['billed'])->toBe(2);
    });

    it('bills only new students when run again', function () {
        feeStudent('Ama');
        app(StartTermBilling::class)->handle($this->bursar, $this->term1);
        feeStudent('Late');

        $second = app(StartTermBilling::class)->handle($this->bursar, $this->term1)->fresh();

        expect($second->total_students)->toBe(1)
            ->and(Bill::count())->toBe(2)
            ->and(fn () => app(StartTermBilling::class)->handle($this->bursar, $this->term1))->toThrow(ValidationException::class, 'already has a bill');
    });

    it('will not start a second run while one is in progress', function () {
        Queue::fake();
        feeStudent();
        app(StartTermBilling::class)->handle($this->bursar, $this->term1);

        expect(fn () => app(StartTermBilling::class)->handle($this->bursar, $this->term1))->toThrow(ValidationException::class, 'already being billed');
    });

    it('survives a chunk running twice', function () {
        Queue::fake();
        $ama = feeStudent('Ama');
        $kofi = feeStudent('Kofi');
        $run = app(StartTermBilling::class)->handle($this->bursar, $this->term1);

        app(BillRunStudents::class)->handle($run->id, [$ama->id, $kofi->id]);
        app(BillRunStudents::class)->handle($run->id, [$ama->id, $kofi->id]);

        expect(Bill::count())->toBe(2)
            ->and(LedgerEntry::count())->toBe(2)
            ->and($run->fresh()->status)->toBe(BillingRunStatus::Completed)
            ->and($run->fresh()->skippedCount())->toBe(0);
    });

    it('runs the chunk job in its school with no school set', function () {
        // Built by hand, not started: Queue::fake() would also swallow dispatch_sync.
        $ama = feeStudent();
        $run = new BillingRun(['term_id' => $this->term1->id]);
        $run->forceFill(['total_students' => 1])->save();
        app(CurrentSchool::class)->clear();

        dispatch_sync(new BillStudentsChunk($this->school->id, $run->id, [$ama->id]));

        app(CurrentSchool::class)->set($this->school);
        expect(Bill::where('student_id', $ama->id)->exists())->toBeTrue();
    });
});

describe('screens', function () {
    beforeEach(function () {
        $this->bursar = staffWith(RoleKey::Bursar);
        $this->head = staffWith(RoleKey::Headteacher);
        $this->onSchool($this->school);
    });

    it('previews the term and bills from the billing screen', function () {
        feeStudent('Ama');
        pupilIn($this->p2, 'Kojo');

        Livewire::actingAs($this->bursar)->test(Billing::class)
            ->assertSet('termId', (string) $this->term1->id)
            ->assertSee('1 student has no fee structure')
            ->assertSee('GH₵1,100.00')
            ->call('start')
            ->assertSee('1 of 2 billed, 1 skipped')
            ->assertSee('Kojo Mensah: No Primary 2 (Day) fee structure');

        expect(Bill::count())->toBe(1);
    });

    it('lets the headteacher look but not bill', function () {
        feeStudent();

        $this->actingAs($this->head)->get('http://greenhill.localhost/fees/billing')->assertOk()->assertDontSee('Bill 1 student');
        Livewire::actingAs($this->head)->test(Billing::class)->call('start')->assertForbidden();
        expect(Bill::count())->toBe(0);
    });

    it('shows the bill and the balance on the student page', function () {
        $ama = feeStudent();
        bill($ama);

        Livewire::actingAs($this->head)->test(Fees::class, ['studentId' => $ama->id])
            ->assertSee('Owes: GH₵1,100.00')
            ->assertSee('Tuition')
            ->assertSee('GH₵1,100.00 by 8 Sep');
    });
});
