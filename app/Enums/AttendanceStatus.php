<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Excused => 'Excused',
        };
    }

    /**
     * Counts as being in school (late students are in school).
     */
    public function isPresent(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
