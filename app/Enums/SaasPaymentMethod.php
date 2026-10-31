<?php

namespace App\Enums;

/**
 * How a school paid Orange Tree.
 */
enum SaasPaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case MobileMoney = 'mobile_money';
    case Cheque = 'cheque';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::MobileMoney => 'Mobile money',
            self::Cheque => 'Cheque',
            self::Cash => 'Cash',
        };
    }
}
