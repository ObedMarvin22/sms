<?php

namespace App\Enums;

use App\Support\Money\Money;
use App\Support\Money\Percent;

/**
 * How a discount or scholarship reduces a bill: a percentage (value in basis points) or a fixed
 * amount (value in pesewas).
 */
enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage',
            self::Fixed => 'Fixed amount',
        };
    }

    /**
     * "12.5%" or "GH₵200.00".
     */
    public function format(int $value): string
    {
        return match ($this) {
            self::Percent => Percent::format($value),
            self::Fixed => Money::pesewas($value)->format(),
        };
    }

    /**
     * "10% of Tuition, PTA dues" or "GH₵200.00 off the bill".
     *
     * @param  list<int>|null  $feeItemIds  null = every item on the bill
     * @param  array<int, string>  $itemNames  fee item id => name
     */
    public function describe(int $value, ?array $feeItemIds, array $itemNames): string
    {
        $on = $feeItemIds === null
            ? 'the bill'
            : implode(', ', array_map(fn (int $id) => $itemNames[$id] ?? '(removed item)', $feeItemIds));

        return $this === self::Percent ? $this->format($value)." of {$on}" : $this->format($value)." off {$on}";
    }

    /**
     * For a form field: "12.5" or "200.00".
     */
    public function plain(int $value): string
    {
        return match ($this) {
            self::Percent => Percent::plain($value),
            self::Fixed => Money::pesewas($value)->plain(),
        };
    }
}
