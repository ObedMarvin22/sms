<?php

namespace App\Enums;

enum GuardianRelationship: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Grandparent = 'grandparent';
    case AuntUncle = 'aunt_uncle';
    case Sibling = 'sibling';
    case LegalGuardian = 'legal_guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mother => 'Mother',
            self::Father => 'Father',
            self::Grandparent => 'Grandparent',
            self::AuntUncle => 'Aunt or uncle',
            self::Sibling => 'Brother or sister',
            self::LegalGuardian => 'Legal guardian',
            self::Other => 'Other',
        };
    }
}
