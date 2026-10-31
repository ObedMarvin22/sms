<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\FindParentChild;
use App\Actions\ParentApp\StartParentPayment;
use App\Http\Resources\V1\MoneyData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Starts an online payment. The app opens authorization_url (Paystack's page); the payment is
 * recorded once Paystack confirms it (webhook, return page or the nightly check).
 */
class ChildPaymentController extends ParentController
{
    public function __invoke(Request $request, int $student, FindParentChild $find, StartParentPayment $start): JsonResponse
    {
        $request->validate(['amount' => ['required', 'string', 'max:20']]);
        $guardian = $this->guardian($request);
        $link = $find->handle($guardian, $student, fees: true);

        $result = $start->handle($guardian, $link->student, $request->string('amount')->toString());
        $transaction = $result['transaction'];

        return response()->json(['data' => [
            'reference' => $transaction->reference,
            'authorization_url' => $result['authorization_url'],
            'amount' => MoneyData::of($transaction->amount),
            'charge' => MoneyData::of($transaction->charge),
            'total' => MoneyData::of($transaction->total),
        ]], 201);
    }
}
