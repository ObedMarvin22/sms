<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\Pdf\FeeDocuments;
use Symfony\Component\HttpFoundation\Response;

/**
 * A payment's receipt as an A5 PDF. The payment is bound through the school scope, so another
 * school's receipt is a 404. Parents download the same receipt through the API.
 */
class ReceiptController extends Controller
{
    public function __invoke(FeeDocuments $documents, Payment $payment): Response
    {
        return $documents->receipt($payment);
    }
}
