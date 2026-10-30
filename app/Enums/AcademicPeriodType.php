<?php

namespace App\Enums;

/**
 * How a school divides its year (SPEC section 6). Stored in SchoolSettings ("academic_period").
 */
enum AcademicPeriodType: string
{
    case Term = 'term';
    case Semester = 'semester';

    public function label(): string
    {
        return match ($this) {
            self::Term => 'Terms',
            self::Semester => 'Semesters',
        };
    }

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Term => 3,
            self::Semester => 2,
        };
    }

    public function periodName(int $sequence): string
    {
        $ordinal = [1 => 'First', 2 => 'Second', 3 => 'Third'][$sequence] ?? (string) $sequence;

        return $ordinal.' '.($this === self::Term ? 'term' : 'semester');
    }
}
