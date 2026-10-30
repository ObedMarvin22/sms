<?php

namespace App\Http\Controllers;

use App\Actions\Paystack\HandlePaystackWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /webhooks/paystack on the main domain (one route for every school). CSRF is off for it
 * (bootstrap/app.php); the Paystack signature is what proves it came from Paystack.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, HandlePaystackWebhook $handle): Response
    {
        $ok = $handle->handle($request->getContent(), $request->header('x-paystack-signature'));

        return response($ok ? 'OK' : 'Invalid signature', $ok ? 200 : 401);
    }
}
