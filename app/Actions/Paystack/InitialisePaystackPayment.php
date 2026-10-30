<?php

namespace App\Actions\Paystack;

use App\Actions\Fees\FeeRules;
use App\Enums\PaystackTransactionStatus;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\PaystackException;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starts an online payment for a student: stores the attempt under a unique reference and asks
 * Paystack for a payment page, with the school's subaccount so the money settles to the school.
 * Used by the parent app (step 6) and by staff sending a payment link.
 */
class InitialisePaystackPayment
{
    public function __construct(private CurrentSchool $currentSchool, private PaystackClient $paystack) {}

    /**
     * @return array{transaction: PaystackTransaction, authorization_url: string}
     */
    public function handle(Student $student, string $amount, string $callbackUrl, ?Guardian $payer = null, ?User $initiatedBy = null): array
    {
        $school = $this->currentSchool->require();
        if ($school->paystack_subaccount_code === null) {
            throw ValidationException::withMessages(['amount' => 'This school cannot take online payments yet. Orange Tree sets it up in the console.']);
        }

        $amount = FeeRules::amount($amount, 'amount');
        $total = PaystackCharge::payer() === PaystackCharge::PARENT ? PaystackCharge::grossUp($amount, PaystackCharge::basisPoints()) : $amount;

        $transaction = new PaystackTransaction([
            'student_id' => $student->id,
            'reference' => "OT{$school->id}-".Str::ulid(),
            'amount' => $amount,
            'charge' => $total->minus($amount),
            'total' => $total,
            'email' => $this->email($student, $payer),
            'initiated_by' => $initiatedBy?->id,
        ]);
        $transaction->save();

        try {
            $page = $this->paystack->initialize($transaction->email, $total->pesewas, $transaction->reference,
                $school->paystack_subaccount_code, $callbackUrl, [
                    'school_id' => $school->id,
                    'student_id' => $student->id,
                    'transaction_id' => $transaction->id,
                    'student' => $student->full_name,
                    'admission_number' => $student->admission_number,
                ]);
        } catch (PaystackException $e) {
            $transaction->forceFill(['status' => PaystackTransactionStatus::Failed, 'attention' => mb_substr($e->getMessage(), 0, 255)])->save();

            throw ValidationException::withMessages(['amount' => 'Paystack: '.$e->getMessage()]);
        }

        return ['transaction' => $transaction, 'authorization_url' => $page['authorization_url']];
    }

    /**
     * Paystack needs an email; parents sign in with a phone. The payer's (or primary guardian's)
     * email if we have it, else an address made from their phone on the school's domain.
     */
    private function email(Student $student, ?Guardian $payer): string
    {
        $payer ??= GuardianStudent::with('guardian')->where('student_id', $student->id)->where('is_primary', true)->first()?->guardian;
        if ($payer?->email !== null && filter_var($payer->email, FILTER_VALIDATE_EMAIL)) {
            return $payer->email;
        }

        $school = $this->currentSchool->require();
        $local = $payer !== null ? ltrim($payer->phone, '+') : "student{$student->id}";

        return "{$local}@{$school->subdomain}.".config('app.base_domain');
    }
}
