<?php

namespace App\Enums;

/**
 * Where a mark sheet stands once it has been submitted at least once. (A sheet never
 * submitted has no record and is simply open.)
 */
enum SheetStatus: string
{
    case Submitted = 'submitted';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Reopened => 'Reopened',
        };
    }
}
