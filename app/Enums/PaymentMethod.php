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
    // Recorded only by the system after Paystack confirms it (SettlePaystackTransaction).
    case Paystack = 'paystack';

    /**
     * Methods staff record by hand.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Cash, self::Bank, self::MobileMoney];
    }

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank deposit',
            self::MobileMoney => 'Mobile money',
            self::Paystack => 'Online (Paystack)',
        };
    }

    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }

    public function needsProof(): bool
    {
        return $this === self::Bank || $this === self::MobileMoney;
    }

    public function referenceLabel(): string
    {
        return match ($this) {
            self::Cash => 'Reference',
            self::Bank => 'Deposit slip number',
            self::MobileMoney => 'Transaction ID',
            self::Paystack => 'Paystack reference',
        };
    }
}
