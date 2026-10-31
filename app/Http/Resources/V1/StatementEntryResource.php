<?php

namespace App\Http\Resources\V1;

use App\Models\LedgerEntry;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One statement line (a bill, payment, adjustment or reversal) and the balance after it.
 *
 * @property array{entry: LedgerEntry, balance: Money} $resource
 */
class StatementEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource['entry'];

        return [
            'date' => $entry->entry_date->toDateString(),
            'type' => $entry->type->value,
            'description' => $entry->description,
            'receipt_number' => $entry->payment?->receipt_number,
            'amount' => MoneyData::of($entry->amount),
            'balance' => MoneyData::of($this->resource['balance']),
        ];
    }
}
