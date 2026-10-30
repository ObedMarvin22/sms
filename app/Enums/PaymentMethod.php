<?php

namespace App\Enums;

/**
 * How money reached the school. Bank deposits and mobile money need a reference (slip number or
 * transaction id) and a photo or PDF of the proof; cash needs neither. Paystack comes in step 4.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'momo';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank deposit',
            self::MobileMoney => 'Mobile money',
        };
    }

    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }

    public function needsProof(): bool
    {
        return $this !== self::Cash;
    }

    public function referenceLabel(): string
    {
        return match ($this) {
            self::Cash => 'Reference',
            self::Bank => 'Deposit slip number',
            self::MobileMoney => 'Transaction ID',
        };
    }
}
