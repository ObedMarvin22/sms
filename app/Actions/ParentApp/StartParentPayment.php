<?php

namespace App\Actions\ParentApp;

use App\Actions\Paystack\InitialisePaystackPayment;
use App\Models\Guardian;
use App\Models\PaystackTransaction;
use App\Models\Student;

/**
 * A parent starts paying fees online: a Paystack page for the amount they chose, with them as
 * the payer. Paystack sends them back to the school's /pay/return page afterwards.
 */
class StartParentPayment
{
    public function __construct(private InitialisePaystackPayment $initialise) {}

    /**
     * @return array{transaction: PaystackTransaction, authorization_url: string}
     */
    public function handle(Guardian $guardian, Student $student, string $amount): array
    {
        return $this->initialise->handle($student, $amount, route('paystack.return'), payer: $guardian, initiatedBy: $guardian->user);
    }
}
