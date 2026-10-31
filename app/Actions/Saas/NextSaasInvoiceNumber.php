<?php

namespace App\Actions\Saas;

use App\Models\SaasInvoiceSequence;
use Illuminate\Support\Facades\DB;

/**
 * Takes the next Orange Tree invoice number: "OT/INV/{year}/{0001}", one series for all schools.
 * The counter row is locked, so invoices issued at the same moment wait their turn. Call it inside
 * the transaction that saves the invoice; a number is never reused, even after a void.
 */
class NextSaasInvoiceNumber
{
    public function handle(int $year): string
    {
        $sequence = SaasInvoiceSequence::where('year', $year)->lockForUpdate()->first();
        if ($sequence === null) {
            DB::table('saas_invoice_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = SaasInvoiceSequence::where('year', $year)->lockForUpdate()->firstOrFail();
        }

        $sequence->last_number++;
        $sequence->save();

        return sprintf('OT/INV/%d/%04d', $year, $sequence->last_number);
    }
}
