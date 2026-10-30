<?php

namespace App\Enums;

/**
 * Education levels a school can offer (SPEC section 6), youngest first.
 */
enum SchoolLevel: string
{
    case Creche = 'creche';
    case Nursery = 'nursery';
    case Kg = 'kg';
    case Primary = 'primary';
    case Jhs = 'jhs';
    case Shs = 'shs';

    public function label(): string
    {
        return match ($this) {
            self::Creche => 'Creche',
            self::Nursery => 'Nursery',
            self::Kg => 'KG',
            self::Primary => 'Primary',
            self::Jhs => 'JHS',
            self::Shs => 'SHS',
        };
    }
}
