<?php

namespace App\Support\Academic;

use App\Enums\SchoolLevel;

/**
 * The usual Ghanaian class names per level, used to generate a new school's classes.
 */
final class DefaultClasses
{
    /**
     * @return list<string>
     */
    public static function for(SchoolLevel $level): array
    {
        return match ($level) {
            SchoolLevel::Creche => ['Creche'],
            SchoolLevel::Nursery => ['Nursery 1', 'Nursery 2'],
            SchoolLevel::Kg => ['KG 1', 'KG 2'],
            SchoolLevel::Primary => array_map(fn (int $n) => "Primary {$n}", range(1, 6)),
            SchoolLevel::Jhs => ['JHS 1', 'JHS 2', 'JHS 3'],
            SchoolLevel::Shs => ['SHS 1', 'SHS 2', 'SHS 3'],
        };
    }

    /**
     * Classes sort by level first (100 apart), then by position within the level.
     */
    public static function sequence(SchoolLevel $level, int $position): int
    {
        $levelIndex = array_search($level, SchoolLevel::cases(), true);

        return ((int) $levelIndex + 1) * 100 + $position;
    }
}
