<?php

namespace App\Enums;

/**
 * How a subject belongs to an SHS programme.
 */
enum SubjectType: string
{
    case Core = 'core';
    case Elective = 'elective';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Core',
            self::Elective => 'Elective',
        };
    }
}
