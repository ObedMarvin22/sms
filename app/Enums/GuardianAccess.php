<?php

namespace App\Enums;

/**
 * What the parent app shows a guardian about one child (enforced from Sprint 4).
 */
enum GuardianAccess: string
{
    case Full = 'full';
    case Limited = 'limited';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full access',
            self::Limited => 'Attendance and notices only',
            self::None => 'Contact only, no app access',
        };
    }
}
