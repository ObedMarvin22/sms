<?php

namespace App\Actions\Payments;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Support\Saas\ReadOnlyGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Undoes a payment recorded by mistake (bounced deposit, wrong student, wrong amount): a
 * reversing entry in the ledger and the payment marked reversed, with the reason. Nothing is
 * edited or deleted, and the receipt number is never reused. Logged (Payment uses LogsActivity).
 */
class ReversePayment
{
    public function handle(User $user, Payment $payment, string $reason): Payment
    {
        // Allowed while read-only, like recording (RecordPayment).
        return app(ReadOnlyGuard::class)->permit(fn () => $this->reverse($user, $payment, $reason));
    }

    private function reverse(User $user, Payment $payment, string $reason): Payment
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Say why it is being reversed (up to 255 characters).']);
        }

        return DB::transaction(function () use ($user, $payment, $reason): Payment {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isReversed()) {
                throw ValidationException::withMessages(['reason' => "Receipt {$payment->receipt_number} is already reversed."]);
            }

            LedgerEntry::create([
                'student_id' => $payment->student_id,
                'type' => LedgerEntryType::Reversal,
                'amount' => $payment->amount,
                'payment_id' => $payment->id,
                'description' => mb_substr("Reversal of receipt {$payment->receipt_number}: {$reason}", 0, 150),
                'entry_date' => now()->toDateString(),
                'created_by' => $user->id,
            ]);

            $payment->forceFill(['reversed_at' => now(), 'reversed_by' => $user->id, 'reversal_reason' => $reason])->save();

            return $payment;
        });
    }
}
