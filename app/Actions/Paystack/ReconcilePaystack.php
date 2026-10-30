<?php

namespace App\Actions\Paystack;

use App\Enums\PaystackTransactionStatus;
use App\Models\PaystackTransaction;
use App\Services\Paystack\PaystackException;

/**
 * Nightly check for the current school (paystack:reconcile): asks Paystack again about every
 * transaction from the last DAYS days that is still waiting or was recorded as paid. A payment
 * the webhook missed is recorded; anything that does not match is flagged for a bursar.
 */
class ReconcilePaystack
{
    public const DAYS = 3;

    public function __construct(private SettlePaystackTransaction $settle) {}

    /**
     * @return array{checked: int, recorded: int, flagged: int, errors: int}
     */
    public function handle(): array
    {
        $counts = ['checked' => 0, 'recorded' => 0, 'flagged' => 0, 'errors' => 0];

        $transactions = PaystackTransaction::whereIn('status', [PaystackTransactionStatus::Pending->value, PaystackTransactionStatus::Success->value])
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->orderBy('id')->get();

        foreach ($transactions as $transaction) {
            $counts['checked']++;
            $before = $transaction->payment_id;

            try {
                $after = $this->settle->handle($transaction);
            } catch (PaystackException) {
                $counts['errors']++;

                continue;
            }

            if ($before === null && $after->payment_id !== null) {
                $counts['recorded']++;
            }
            if ($after->status === PaystackTransactionStatus::Attention) {
                $counts['flagged']++;
            }
        }

        if ($counts['recorded'] > 0 || $counts['flagged'] > 0) {
            activity('payments')->event('paystack_reconciled')->withProperties($counts)
                ->log("Paystack check: {$counts['recorded']} recorded, {$counts['flagged']} flagged");
        }

        return $counts;
    }
}
