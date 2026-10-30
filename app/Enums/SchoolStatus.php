<?php

namespace App\Enums;

/**
 * Lifecycle of a school's subscription. Stored as a string in schools.status.
 */
enum SchoolStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }
}
