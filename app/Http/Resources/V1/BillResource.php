<?php

namespace App\Http\Resources\V1;

use App\Models\Bill;
use App\Models\BillInstalment;
use App\Models\BillLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bill
 */
class BillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'term' => $this->term->name,
            'class' => $this->schoolClass->name,
            'residence' => $this->residence->value,
            'issued_on' => $this->issued_at->toDateString(),
            'lines' => $this->lines->map(fn (BillLine $line) => [
                'description' => $line->description,
                'kind' => $line->kind->value,
                'amount' => MoneyData::of($line->amount),
            ])->all(),
            'gross' => MoneyData::of($this->gross),
            'reductions' => MoneyData::of($this->reductions),
            'net' => MoneyData::of($this->net),
            'arrears' => MoneyData::of($this->arrears),
            'payable' => MoneyData::of($this->payable()),
            'instalments' => $this->instalments->map(fn (BillInstalment $instalment) => [
                'due_on' => $instalment->due_on->toDateString(),
                'amount' => MoneyData::of($instalment->amount),
            ])->all(),
        ];
    }
}
