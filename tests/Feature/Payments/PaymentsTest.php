<?php

use App\Actions\Billing\IssueBill;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Payments\AdjustBalance;
use App\Actions\Payments\NextReceiptNumber;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReversePayment;
use App\Enums\LedgerEntryType;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Livewire\Fees\Payments;
use App\Livewire\Students\Fees;
use App\Models\Activity;
use App\Models\FeeItem;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Fees/helpers.php';

beforeEach(function () {
    setUpFeeSchool();
    Storage::fake('local');

    app(SaveFeeStructure::class)->handle($this->term1, $this->p1, Residence::Day, [
        FeeItem::where('name', 'Tuition')->sole()->id => '1000',
        FeeItem::where('name', 'PTA dues')->sole()->id => '100',
    ]);
    $this->ama = feeStudent('Ama');
    app(IssueBill::class)->handle($this->ama, $this->term1); // owes GH₵1,100.00
    $this->bursar = staffWith(RoleKey::Bursar);
});

/**
 * @param  array<string, mixed>  $input
 */
function pay(Student $student, array $input = [], ?UploadedFile $proof = null, ?User $by = null): Payment
{
    return app(RecordPayment::class)->handle($by ?? test()->bursar, $student,
        array_merge(['amount' => '500', 'method' => 'cash', 'paid_on' => now()->toDateString()], $input), $proof);
}

function slip(): UploadedFile
{
    return UploadedFile::fake()->image('slip.jpg');
}

describe('recording', function () {
    it('issues a numbered receipt and credits the ledger', function () {
        $payment = pay($this->ama);

        $credit = LedgerEntry::where('payment_id', $payment->id)->sole();
        expect($payment->receipt_number)->toMatch('#^[A-Z]+/R/2026/00001$#')
            ->and($credit->type)->toBe(LedgerEntryType::Payment)
            ->and($credit->amount->pesewas)->toBe(-50_000)
            ->and(LedgerEntry::balanceFor($this->ama->id)->format())->toBe('GH₵600.00')
            ->and(Activity::where('log_name', 'payments')->where('subject_id', $payment->id)->where('event', 'created')->exists())->toBeTrue();
    });

    it('numbers receipts in order, never reuses one, and restarts each year', function () {
        $first = pay($this->ama);
        app(ReversePayment::class)->handle($this->bursar, $first, 'Wrong amount');
        $second = pay($this->ama, ['amount' => '400']);

        $this->travelTo('2027-01-05 09:00:00');
        $nextYear = pay($this->ama, ['amount' => '100']);

        expect(substr($first->receipt_number, -12))->toBe('R/2026/00001')
            ->and(substr($second->receipt_number, -12))->toBe('R/2026/00002')
            ->and(substr($nextYear->receipt_number, -12))->toBe('R/2027/00001');
    });

    it('lets a parent pay more than is owed, carrying the credit to the next bill', function () {
        app(SaveFeeStructure::class)->handle($this->term2, $this->p1, Residence::Day, [FeeItem::where('name', 'Tuition')->sole()->id => '1000']);
        pay($this->ama, ['amount' => '1500']);

        $next = app(IssueBill::class)->handle($this->ama, $this->term2);

        expect($next->arrears->format())->toBe('-GH₵400.00')
            ->and($next->payable()->format())->toBe('GH₵600.00');
    });

    it('needs a reference and proof for bank deposits and mobile money, but not for cash', function () {
        expect(fn () => pay($this->ama, ['method' => 'bank'], slip()))->toThrow(ValidationException::class, 'Deposit slip number')
            ->and(fn () => pay($this->ama, ['method' => 'momo', 'reference' => '1234567890']))->toThrow(ValidationException::class, 'Attach a photo or PDF');

        $momo = pay($this->ama, ['method' => 'momo', 'reference' => ' 12345 67890 '], slip());

        expect($momo->reference)->toBe('1234567890')
            ->and(Storage::disk('local')->exists((string) $momo->proof_path))->toBeTrue()
            ->and(pay($this->ama, ['amount' => '10'])->proof_path)->toBeNull();
    });

    it('refuses a slip or transaction recorded before', function () {
        $first = pay($this->ama, ['method' => 'bank', 'reference' => 'GCB-00981'], slip());

        expect(fn () => pay($this->ama, ['method' => 'bank', 'reference' => 'gcb-00981'], slip()))
            ->toThrow(ValidationException::class, "already on receipt {$first->receipt_number}");
    });

    it('refuses bad proof and keeps no file when nothing is saved', function () {
        expect(fn () => pay($this->ama, ['method' => 'bank', 'reference' => 'X1'], UploadedFile::fake()->create('slip.docx', 10)))
            ->toThrow(ValidationException::class, 'JPG, PNG or PDF');
        expect(fn () => pay($this->ama, ['method' => 'bank', 'reference' => 'X2'], UploadedFile::fake()->create('slip.pdf', 6 * 1024, 'application/pdf')))
            ->toThrow(ValidationException::class, 'up to 5 MB');

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('deletes the uploaded proof if saving the payment fails', function () {
        app()->instance(NextReceiptNumber::class, Mockery::mock(NextReceiptNumber::class)
            ->shouldReceive('handle')->andThrow(new RuntimeException('database went away'))->getMock());

        expect(fn () => pay($this->ama, ['method' => 'bank', 'reference' => 'X3'], slip()))->toThrow(RuntimeException::class);

        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Payment::count())->toBe(0);
    });

    it('accepts dates up to 30 days back, never in the future', function (string $date, bool $ok) {
        $attempt = fn () => pay($this->ama, ['paid_on' => $date]);

        $ok ? expect($attempt()->paid_on->toDateString())->toBe($date) : expect($attempt)->toThrow(ValidationException::class);
    })->with([
        'today' => ['2026-10-28', true],
        '30 days back' => ['2026-09-28', true],
        '31 days back' => ['2026-09-27', false],
        'tomorrow' => ['2026-10-29', false],
        'not a date' => ['28/10/2026', false],
    ]);

    it('refuses amounts that are not money', function (string $amount) {
        expect(fn () => pay($this->ama, ['amount' => $amount]))->toThrow(ValidationException::class);
        expect(Payment::count())->toBe(0);
    })->with(['0', '-50', 'five hundred', '12.345']);

    it('tells the guardians, with the new balance', function () {
        Notification::fake();
        $mother = app(AddGuardian::class)->handle($this->ama, ['name' => 'Akosua Mensah', 'phone' => '0244000001', 'relationship' => 'mother']);
        $contactOnly = app(AddGuardian::class)->handle($this->ama, ['name' => 'Yaw Mensah', 'phone' => '0244000002', 'relationship' => 'father', 'access_level' => 'none']);

        pay($this->ama);

        Notification::assertSentTo($mother, PaymentReceived::class, fn (PaymentReceived $n) => str_contains($n->toLog($mother), 'GH₵500.00 for Ama')
            && str_contains($n->toLog($mother), 'Balance: GH₵600.00'));
        Notification::assertNotSentTo($contactOnly, PaymentReceived::class);
    });
});

describe('corrections', function () {
    it('reverses a payment: the balance comes back, the receipt is kept and marked', function () {
        $payment = pay($this->ama);
        app(ReversePayment::class)->handle($this->bursar, $payment, 'Deposit bounced');

        $payment->refresh();
        expect(LedgerEntry::balanceFor($this->ama->id)->format())->toBe('GH₵1,100.00')
            ->and($payment->isReversed())->toBeTrue()
            ->and($payment->reversal_reason)->toBe('Deposit bounced')
            ->and(LedgerEntry::where('payment_id', $payment->id)->pluck('type')->all())->toBe([LedgerEntryType::Payment, LedgerEntryType::Reversal])
            ->and(fn () => app(ReversePayment::class)->handle($this->bursar, $payment, 'Again'))->toThrow(ValidationException::class, 'already reversed')
            ->and(fn () => app(ReversePayment::class)->handle($this->bursar, pay($this->ama), ' '))->toThrow(ValidationException::class);
    });

    it('never edits or deletes a payment', function () {
        $payment = pay($this->ama);

        expect(fn () => $payment->update(['amount' => Money::pesewas(1)]))->toThrow(LogicException::class, 'never edited')
            ->and(fn () => $payment->delete())->toThrow(LogicException::class, 'never deleted');
    });

    it('adjusts a balance up or down with a logged reason', function () {
        app(AdjustBalance::class)->handle($this->bursar, $this->ama, 'charge', '150', 'Uniform added');
        app(AdjustBalance::class)->handle($this->bursar, $this->ama, 'credit', '50', 'Waived by proprietor');

        expect(LedgerEntry::balanceFor($this->ama->id)->format())->toBe('GH₵1,200.00')
            ->and(Activity::where('log_name', 'payments')->where('event', 'balance_adjusted')->count())->toBe(2)
            ->and(fn () => app(AdjustBalance::class)->handle($this->bursar, $this->ama, 'charge', '10', ''))->toThrow(ValidationException::class);
    });
});

describe('receipts and screens', function () {
    beforeEach(function () {
        $this->head = staffWith(RoleKey::Headteacher);
        $this->teacher = staffWith(RoleKey::ClassTeacher);
        $this->onSchool($this->school);
    });

    it('downloads the receipt as a PDF', function () {
        $payment = pay($this->ama);

        $response = $this->actingAs($this->head)->get(route('receipts.pdf', $payment))->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($response->headers->get('Content-Disposition'))->toContain('r-2026-00001.pdf')
            ->and(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');
    });

    it('keeps receipts and proof within the school and from staff without finance access', function () {
        $payment = pay($this->ama, ['method' => 'momo', 'reference' => 'TX1'], slip());
        $other = School::factory()->active()->create(['subdomain' => 'hillview']);
        $theirs = app(CurrentSchool::class)->runFor($other, fn () => Payment::factory()->create(['school_id' => $other->id]));

        $this->actingAs($this->head)->get(route('payments.proof', $payment))->assertOk();
        $this->actingAs($this->head)->get(route('receipts.pdf', $theirs->id))->assertNotFound();
        $this->actingAs($this->teacher)->get(route('receipts.pdf', $payment))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('fees.payments'))->assertForbidden();
    });

    it('records a payment from the payments screen', function () {
        Livewire::actingAs($this->bursar)->test(Payments::class)
            ->call('startRecord')
            ->set('studentSearch', 'ama')
            ->assertSee($this->ama->admission_number)
            ->call('pickStudent', $this->ama->id)
            ->assertSee('Owes: GH₵1,100.00')
            ->set('form.amount', '1,200')
            ->assertSee('The extra GH₵100.00 will be a credit')
            ->call('record')
            ->assertHasNoErrors()
            ->assertSee('GH₵1,200.00');

        expect(Payment::sole()->amount->format())->toBe('GH₵1,200.00');
    });

    it('opens the record form for a student from their page', function () {
        Livewire::withQueryParams(['student' => $this->ama->id])->actingAs($this->bursar)->test(Payments::class)
            ->assertSee('Owes: GH₵1,100.00');
    });

    it('reverses from the payments screen', function () {
        $payment = pay($this->ama);

        Livewire::actingAs($this->bursar)->test(Payments::class)
            ->call('startReverse', $payment->id)
            ->set('reason', 'Wrong student')
            ->call('reverse')
            ->assertHasNoErrors()
            ->assertSee('Reversed');
    });

    it('lets the headteacher see payments but not record or reverse them', function () {
        $payment = pay($this->ama);

        Livewire::actingAs($this->head)->test(Payments::class)
            ->assertSee($payment->receipt_number)
            ->assertDontSee('Record payment')
            ->call('startRecord')->assertForbidden();
        Livewire::actingAs($this->head)->test(Payments::class)->call('startReverse', $payment->id)->assertForbidden();
    });

    it('shows the statement with a running balance and adjusts from the student page', function () {
        pay($this->ama);

        Livewire::actingAs($this->bursar)->test(Fees::class, ['studentId' => $this->ama->id])
            ->assertSee('Statement (2 entries)')
            ->assertSee('GH₵600.00')
            ->call('startAdjust')
            ->set('adjust.direction', 'credit')
            ->set('adjust.amount', '100')
            ->set('adjust.reason', 'Waived')
            ->call('saveAdjustment')
            ->assertHasNoErrors()
            ->assertSee('Owes: GH₵500.00');

        Livewire::actingAs($this->head)->test(Fees::class, ['studentId' => $this->ama->id])->call('startAdjust')->assertForbidden();
    });
});
