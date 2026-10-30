<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPayment;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The deposit slip or mobile money screenshot behind a payment, from the private disk. The
 * payment is bound through the school scope, so another school's proof is a 404.
 */
class PaymentProofController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        $disk = Storage::disk(RecordPayment::PROOF_DISK);
        abort_if($payment->proof_path === null || ! $disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path, "proof-{$payment->id}.".pathinfo($payment->proof_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
