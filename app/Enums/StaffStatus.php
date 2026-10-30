<?php

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Left => 'Left',
        };
    }
}
