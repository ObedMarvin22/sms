<?php

namespace Database\Factories;

use App\Enums\SaasPaymentMethod;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasPayment>
 */
class SaasPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'saas_invoice_id' => SaasInvoice::factory(),
            'school_id' => fn (array $a) => SaasInvoice::whereKey($a['saas_invoice_id'])->value('school_id'),
            'amount' => 50000,
            'paid_on' => now()->toDateString(),
            'method' => SaasPaymentMethod::BankTransfer,
        ];
    }
}
