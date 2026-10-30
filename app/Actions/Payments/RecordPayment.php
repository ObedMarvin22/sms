<?php

namespace App\Actions\Payments;

use App\Actions\Fees\FeeRules;
use App\Enums\GuardianAccess;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Guardian;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Records money received for a student: the payment with its receipt number and a credit in
 * the ledger, in one transaction under a lock on the student. Then tells the guardians.
 *
 * Rules: amount GH₵0.01–1,000,000; bank and mobile money need a reference (recorded once per
 * method) and proof (JPG, PNG or PDF up to 5 MB); paid at most 30 days ago and never in the
 * future. Paying more than is owed is allowed: the student goes into credit.
 */
class RecordPayment
{
    public const MAX_DAYS_BACK = 30;

    public const PROOF_DISK = 'local';

    public function __construct(private CurrentSchool $currentSchool, private NextReceiptNumber $nextReceiptNumber) {}

    /**
     * @param  array{amount?: string, method?: string, reference?: string|null, paid_on?: string, note?: string|null}  $input
     */
    public function handle(User $user, Student $student, array $input, ?UploadedFile $proof = null): Payment
    {
        $amount = FeeRules::amount((string) ($input['amount'] ?? ''), 'amount');
        $method = PaymentMethod::tryFrom((string) ($input['method'] ?? '')) ?? throw ValidationException::withMessages(['method' => 'Choose how it was paid.']);
        $reference = $this->reference($method, (string) ($input['reference'] ?? ''));
        $paidOn = $this->paidOn((string) ($input['paid_on'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > 255) {
            throw ValidationException::withMessages(['note' => 'Keep the note under 255 characters.']);
        }
        $this->checkProof($method, $proof);
        if ($student->trashed()) {
            throw ValidationException::withMessages(['student' => 'This student has been removed.']);
        }

        $school = $this->currentSchool->require();
        $proofPath = $proof?->storeAs("schools/{$school->id}/payments", Str::uuid().'.'.$proof->extension(), self::PROOF_DISK) ?: null;

        try {
            $payment = DB::transaction(function () use ($user, $student, $amount, $method, $reference, $paidOn, $note, $proofPath): Payment {
                Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

                $payment = Payment::create([
                    'student_id' => $student->id,
                    // Numbered by the year the receipt is issued, not the (possibly earlier) payment date.
                    'receipt_number' => $this->nextReceiptNumber->handle((int) now()->year),
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'paid_on' => $paidOn,
                    'proof_path' => $proofPath,
                    'note' => $note === '' ? null : $note,
                    'recorded_by' => $user->id,
                ]);

                LedgerEntry::create([
                    'student_id' => $student->id,
                    'type' => LedgerEntryType::Payment,
                    'amount' => Money::pesewas(-$amount->pesewas),
                    'payment_id' => $payment->id,
                    'description' => "Payment, receipt {$payment->receipt_number} ({$method->label()})",
                    'entry_date' => $paidOn,
                    'created_by' => $user->id,
                ]);

                return $payment;
            }, attempts: 3);
        } catch (Throwable $e) {
            if ($proofPath !== null) {
                Storage::disk(self::PROOF_DISK)->delete($proofPath); // never keep proof for a payment that was not saved
            }
            throw $e;
        }

        $guardians = Guardian::whereHas('links', fn ($q) => $q->where('student_id', $student->id)
            ->where('access_level', '!=', GuardianAccess::None->value))->get();
        if ($guardians->isNotEmpty()) {
            Notification::send($guardians, new PaymentReceived($payment, LedgerEntry::balanceFor($student->id), $school));
        }

        return $payment;
    }

    private function reference(PaymentMethod $method, string $reference): ?string
    {
        $reference = mb_strtoupper(preg_replace('/\s+/', '', $reference) ?? '');

        if ($reference === '') {
            if ($method->needsReference()) {
                throw ValidationException::withMessages(['reference' => "Enter the {$method->referenceLabel()}."]);
            }

            return null;
        }
        if (mb_strlen($reference) > 60) {
            throw ValidationException::withMessages(['reference' => 'Keep the reference under 60 characters.']);
        }

        $earlier = Payment::where('method', $method->value)->where('reference', $reference)->first();
        if ($earlier !== null) {
            throw ValidationException::withMessages(['reference' => "This {$method->referenceLabel()} is already on receipt {$earlier->receipt_number}."]);
        }

        return $reference;
    }

    private function paidOn(string $date): CarbonImmutable
    {
        try {
            $paidOn = CarbonImmutable::createFromFormat('!Y-m-d', $date) ?: throw new InvalidArgumentException;
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['paid_on' => 'Choose the date it was paid.']);
        }

        $today = CarbonImmutable::today();
        if ($paidOn->gt($today) || $paidOn->lt($today->subDays(self::MAX_DAYS_BACK))) {
            throw ValidationException::withMessages(['paid_on' => 'The date must be today or in the last '.self::MAX_DAYS_BACK.' days.']);
        }

        return $paidOn;
    }

    private function checkProof(PaymentMethod $method, ?UploadedFile $proof): void
    {
        if ($proof === null) {
            if ($method->needsProof()) {
                throw ValidationException::withMessages(['proof' => 'Attach a photo or PDF of the '.mb_strtolower($method->label()).' proof.']);
            }

            return;
        }

        if (! in_array(mb_strtolower($proof->extension()), ['jpg', 'jpeg', 'png', 'pdf'], true) || $proof->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['proof' => 'Attach a JPG, PNG or PDF of up to 5 MB.']);
        }
    }
}
