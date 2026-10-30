<?php

namespace App\Jobs;

use App\Actions\Paystack\SettlePaystackTransaction;
use App\Models\PaystackEvent;
use App\Models\PaystackTransaction;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Processes a stored Paystack webhook event. Only charge.success matters; others are marked
 * processed. The school is found from OUR transaction reference, never from data in the event.
 */
class ProcessPaystackEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before a retry (Paystack may be briefly unreachable). */
    public int $backoff = 60;

    public int $timeout = 45;

    public function __construct(public int $eventId) {}

    public function handle(CurrentSchool $currentSchool, SettlePaystackTransaction $settle): void
    {
        $event = PaystackEvent::find($this->eventId);
        if ($event === null || $event->processed_at !== null) {
            return;
        }

        if ($event->event !== 'charge.success' || $event->reference === null) {
            $event->forceFill(['processed_at' => now()])->save();

            return;
        }

        // Platform code: the event has no school yet. The reference is unique across schools,
        // so this finds exactly one transaction, and we then work inside its school.
        $transaction = PaystackTransaction::withoutGlobalScopes()->where('reference', $event->reference)->first();
        if ($transaction === null) {
            $event->forceFill(['processed_at' => now(), 'error' => 'No transaction with this reference.'])->save();

            return;
        }

        $currentSchool->runFor(School::findOrFail($transaction->school_id), function () use ($settle, $transaction): void {
            $settle->handle(PaystackTransaction::findOrFail($transaction->id));
        });

        $event->forceFill(['processed_at' => now(), 'error' => null])->save();
    }
}
