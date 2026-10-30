<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Withdrawn = 'withdrawn';
    case Transferred = 'transferred';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Withdrawn => 'Withdrawn',
            self::Transferred => 'Transferred out',
            self::Graduated => 'Graduated',
        };
    }
}
