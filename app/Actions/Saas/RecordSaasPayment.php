<?php

namespace App\Actions\Saas;

use App\Actions\Fees\FeeRules;
use App\Enums\SaasPaymentMethod;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records money a school paid Orange Tree against an invoice. Part payments are fine; paying more
 * than the balance is refused. Logged; never changed afterwards.
 */
class RecordSaasPayment
{
    /**
     * @param  array{amount?: mixed, paid_on?: mixed, method?: mixed, reference?: mixed, note?: mixed}  $input
     */
    public function handle(User $by, SaasInvoice $invoice, array $input): SaasPayment
    {
        $data = Validator::make($input, [
            'amount' => ['required', 'string'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(SaasPaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['paid_on.before_or_equal' => 'The payment date cannot be in the future.'])->validate();

        $amount = FeeRules::amount($data['amount'], 'amount');

        return DB::transaction(function () use ($by, $invoice, $data, $amount): SaasPayment {
            // Lock the invoice so two payments recorded together cannot overpay it.
            $invoice = SaasInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->voided_at !== null) {
                throw ValidationException::withMessages(['amount' => 'This invoice was voided.']);
            }
            if ($amount->pesewas > $invoice->balance()->pesewas) {
                throw ValidationException::withMessages(['amount' => "That is more than the balance of {$invoice->balance()->format()}."]);
            }

            $payment = new SaasPayment;
            $payment->forceFill([
                'saas_invoice_id' => $invoice->id,
                'school_id' => $invoice->school_id,
                'amount' => $amount,
                'paid_on' => $data['paid_on'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'recorded_by' => $by->id,
            ])->save();

            return $payment;
        });
    }
}
