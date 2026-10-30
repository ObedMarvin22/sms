<?php

namespace App\Http\Controllers;

use App\Actions\Paystack\SettlePaystackTransaction;
use App\Enums\PaystackTransactionStatus;
use App\Models\PaystackTransaction;
use App\Services\Paystack\PaystackException;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Where Paystack sends the parent after checkout. Asks Paystack straight away (the webhook may
 * not have arrived yet) and shows the outcome. The reference is looked up in this school only.
 */
class PaystackReturnController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $currentSchool, SettlePaystackTransaction $settle): View
    {
        $transaction = PaystackTransaction::with(['student', 'payment'])->where('reference', (string) $request->query('reference', ''))->first();
        abort_if($transaction === null, 404);

        if ($transaction->status === PaystackTransactionStatus::Pending) {
            try {
                $transaction = $settle->handle($transaction)->load(['student', 'payment']);
            } catch (PaystackException) {
                // Paystack unreachable: the webhook or tonight's check will record it.
            }
        }

        return view('paystack.return', ['school' => $currentSchool->require(), 'transaction' => $transaction]);
    }
}
