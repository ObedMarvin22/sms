<?php

namespace App\Actions\Saas;

use App\Models\SaasInvoice;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an invoice issued by mistake (e.g. a wrong census count), with a reason. Its number is
 * not reused, and the term can then be invoiced again. An invoice with payments cannot be voided.
 */
class VoidSaasInvoice
{
    public function handle(User $by, SaasInvoice $invoice, string $reason): SaasInvoice
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Say why the invoice is voided.']);
        }
        if ($invoice->voided_at !== null) {
            throw ValidationException::withMessages(['reason' => 'This invoice is already void.']);
        }
        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages(['reason' => 'Payments are recorded against this invoice, so it cannot be voided.']);
        }

        $invoice->forceFill([
            'live' => null,
            'voided_at' => now(),
            'void_reason' => mb_substr($reason, 0, 255),
            'voided_by' => $by->id,
        ])->save();

        return $invoice;
    }
}
