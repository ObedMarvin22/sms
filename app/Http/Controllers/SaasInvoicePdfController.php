<?php

namespace App\Http\Controllers;

use App\Models\SaasInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Console: an Orange Tree invoice to a school as an A4 PDF (dompdf). Void invoices print with
 * a VOID stamp.
 */
class SaasInvoicePdfController extends Controller
{
    public function __invoke(SaasInvoice $invoice): Response
    {
        Gate::authorize('view-billing');
        $invoice->load(['school', 'term', 'payments'])->loadSum('payments', 'amount');

        $pdf = Pdf::loadView('pdf.saas-invoice', [
            'invoice' => $invoice,
            'from' => config('saas.invoice'),
        ])->setPaper('a4');

        return $pdf->download('invoice-'.Str::slug(str_replace('/', '-', $invoice->number)).'.pdf');
    }
}
