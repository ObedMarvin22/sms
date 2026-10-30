<?php

namespace App\Enums;

/**
 * Day student or boarder: decides which fee structure a student is billed from.
 */
enum Residence: string
{
    case Day = 'day';
    case Boarding = 'boarding';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Day',
            self::Boarding => 'Boarding',
        };
    }
}
