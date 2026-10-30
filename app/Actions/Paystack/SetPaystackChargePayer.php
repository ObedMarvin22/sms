<?php

namespace App\Actions\Paystack;

use App\Models\School;
use Illuminate\Validation\ValidationException;

/**
 * Whether the school absorbs Paystack's charge (parents pay the fee amount exactly) or passes it
 * on (the amount is grossed up so the school still receives the full fee). Logged.
 */
class SetPaystackChargePayer
{
    public function handle(School $school, string $payer): School
    {
        if (! in_array($payer, [PaystackCharge::SCHOOL, PaystackCharge::PARENT], true)) {
            throw ValidationException::withMessages(['paystack_charge_payer' => 'Choose who pays the online payment charge.']);
        }

        $school->settings = [...($school->settings ?? []), 'paystack_charge_payer' => $payer];
        $school->save();

        activity('schools')->performedOn($school)->event('paystack_charge_payer_set')
            ->withProperties(['payer' => $payer])->log("online charge paid by {$payer}");

        return $school;
    }
}
