<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\FindParentChild;
use App\Models\Payment;
use App\Support\Pdf\FeeDocuments;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The fee statement and receipts as PDFs, the same documents staff print. Full access only.
 * A receipt must be for this child: any other payment is "not found".
 */
class ChildDocumentsController extends ParentController
{
    public function statement(Request $request, int $student, FindParentChild $find, FeeDocuments $documents): Response
    {
        $link = $find->handle($this->guardian($request), $student, fees: true);

        return $documents->statement($link->student);
    }

    public function receipt(Request $request, int $student, int $payment, FindParentChild $find, FeeDocuments $documents): Response
    {
        $find->handle($this->guardian($request), $student, fees: true);

        return $documents->receipt(Payment::where('student_id', $student)->whereKey($payment)->firstOrFail());
    }
}
