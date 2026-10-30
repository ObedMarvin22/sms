<?php

namespace App\Enums;

/**
 * What a ledger entry records. Bills add to what a student owes; payments (step 3) take from
 * it; adjustments and reversals correct mistakes without editing anything.
 */
enum LedgerEntryType: string
{
    case Bill = 'bill';
    case Payment = 'payment';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Bill => 'Bill',
            self::Payment => 'Payment',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }
}
