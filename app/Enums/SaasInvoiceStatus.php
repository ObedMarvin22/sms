<?php

namespace App\Enums;

/**
 * Where an Orange Tree invoice stands. Worked out from payments and dates, not stored.
 */
enum SaasInvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Overdue = 'overdue';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Overdue => 'Overdue',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Unpaid => 'amber',
            self::Overdue => 'red',
            self::Paid => 'green',
            self::Void => 'zinc',
        };
    }
}
