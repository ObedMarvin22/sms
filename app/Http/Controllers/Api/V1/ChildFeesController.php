<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\FindParentChild;
use App\Actions\Payments\BuildStatement;
use App\Http\Resources\V1\BillResource;
use App\Http\Resources\V1\MoneyData;
use App\Http\Resources\V1\StatementEntryResource;
use App\Models\Bill;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bills and the fee statement. Full access only.
 */
class ChildFeesController extends ParentController
{
    public function bills(Request $request, int $student, FindParentChild $find): AnonymousResourceCollection
    {
        $find->handle($this->guardian($request), $student, fees: true);

        $bills = Bill::with(['term', 'schoolClass', 'lines', 'instalments'])
            ->where('student_id', $student)->latest('issued_at')->latest('id')->get();

        return BillResource::collection($bills)->additional(['balance' => MoneyData::of(LedgerEntry::balanceFor($student))]);
    }

    public function statement(Request $request, int $student, FindParentChild $find, BuildStatement $statement): AnonymousResourceCollection
    {
        $find->handle($this->guardian($request), $student, fees: true);

        return StatementEntryResource::collection($statement->handle($student))
            ->additional(['balance' => MoneyData::of(LedgerEntry::balanceFor($student))]);
    }
}
