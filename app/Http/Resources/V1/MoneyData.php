<?php

namespace App\Http\Resources\V1;

use App\Support\Money\Money;

/**
 * Money in the API: whole pesewas for sums, and the text to show (e.g. "GH₵1,000.00").
 */
final class MoneyData
{
    /**
     * @return array{pesewas: int, display: string}|null
     */
    public static function of(?Money $money): ?array
    {
        return $money === null ? null : ['pesewas' => $money->pesewas, 'display' => $money->format()];
    }
}
