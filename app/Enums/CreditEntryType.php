<?php

namespace App\Enums;

enum CreditEntryType: string
{
    case TopUp = 'top_up';
    case Message = 'message';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::TopUp => 'Credits added',
            self::Message => 'SMS',
            self::Refund => 'Returned (SMS failed)',
        };
    }
}
