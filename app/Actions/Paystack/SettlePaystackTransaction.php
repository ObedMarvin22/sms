<?php

namespace App\Actions\Paystack;

use App\Actions\Payments\RecordPayment;
use App\Enums\PaymentMethod;
use App\Enums\PaystackTransactionStatus;
use App\Models\PaystackTransaction;
use App\Services\Paystack\PaystackClient;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Asks Paystack what happened to a transaction and acts on the answer, never on a webhook alone:
 *
 * - paid, right amount and currency, not yet recorded: record the payment (receipt, ledger,
 *   guardian notice) through RecordPayment, once;
 * - paid but the amount or currency differs: flag for a bursar, record nothing;
 * - failed or abandoned before we recorded it: mark failed;
 * - no longer successful after we recorded it: flag for a bursar (never reversed silently).
 *
 * Safe to call any number of times: the transaction row is locked and a recorded payment ends it.
 */
class SettlePaystackTransaction
{
    public function __construct(private PaystackClient $paystack, private RecordPayment $recordPayment) {}

    public function handle(PaystackTransaction $transaction): PaystackTransaction
    {
        $data = $this->paystack->verify($transaction->reference);

        return DB::transaction(function () use ($transaction, $data): PaystackTransaction {
            $transaction = PaystackTransaction::with('student')->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $transaction->forceFill(['gateway_response' => $data, 'verified_at' => now()]);

            $status = (string) ($data['status'] ?? '');
            $amount = (int) ($data['amount'] ?? -1);
            $currency = (string) ($data['currency'] ?? '');

            if ($transaction->payment_id !== null) {
                if ($status !== 'success') {
                    $transaction->forceFill(['status' => PaystackTransactionStatus::Attention,
                        'attention' => "Paystack now reports \"{$status}\" but receipt {$transaction->payment?->receipt_number} was issued. Check with Paystack before reversing."]);
                }
                $transaction->save();

                return $transaction;
            }

            if ($status !== 'success') {
                if (in_array($status, ['failed', 'abandoned', 'reversed'], true)) {
                    $transaction->forceFill(['status' => PaystackTransactionStatus::Failed]);
                }
                $transaction->save();

                return $transaction;
            }

            if ($currency !== 'GHS' || $amount !== $transaction->total->pesewas) {
                $paid = $currency === 'GHS' ? Money::pesewas(max(0, $amount))->format() : "{$amount} {$currency}";
                $transaction->forceFill(['status' => PaystackTransactionStatus::Attention,
                    'attention' => "Paystack received {$paid} but {$transaction->total->format()} was expected. Nothing was recorded."])->save();

                return $transaction;
            }

            $paidAt = is_string($data['paid_at'] ?? null) ? CarbonImmutable::parse($data['paid_at']) : CarbonImmutable::now();
            $payment = $this->recordPayment->handle(null, $transaction->student, [
                'amount' => $transaction->amount->plain(),
                'method' => PaymentMethod::Paystack->value,
                'reference' => $transaction->reference,
                'paid_on' => $paidAt->min(CarbonImmutable::today())->toDateString(),
                'note' => $transaction->charge->isZero() ? null : "Online charge of {$transaction->charge->format()} paid by the parent.",
            ]);

            $transaction->forceFill(['status' => PaystackTransactionStatus::Success, 'payment_id' => $payment->id, 'attention' => null])->save();

            return $transaction;
        });
    }
}
