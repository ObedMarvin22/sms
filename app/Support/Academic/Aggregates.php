<?php

namespace App\Support\Academic;

use App\Enums\SchoolLevel;

/**
 * JHS and SHS aggregates: the points of the core subjects plus the best few others, as Ghana's
 * BECE and WASSCE count them. Lower is better (6 is perfect). Sprint 6 defaults (Claude, for
 * Obed's review); per-school settings can come later.
 *
 * - JHS: English Language, Mathematics, Science, Social Studies + best 2 others (6 subjects).
 * - SHS: English Language, Core Mathematics, Integrated Science + best 3 others (6 subjects);
 *   Social Studies is a core subject but not counted, as in WASSCE aggregates.
 */
final class Aggregates
{
    /** @var array<string, array{core: list<string>, best: int, exclude: list<string>}> */
    public const RULES = [
        'jhs' => ['core' => ['English Language', 'Mathematics', 'Science', 'Social Studies'], 'best' => 2, 'exclude' => []],
        'shs' => ['core' => ['English Language', 'Core Mathematics', 'Integrated Science'], 'best' => 3, 'exclude' => ['Social Studies']],
    ];

    /**
     * @param  array<string, int|null>  $points  subject name => points (null when not graded)
     * @return int|null null when the level has no aggregate, or a core subject or enough others are missing
     */
    public static function for(SchoolLevel $level, array $points): ?int
    {
        $rule = self::RULES[$level->value] ?? null;
        if ($rule === null) {
            return null;
        }

        $total = 0;
        foreach ($rule['core'] as $core) {
            if (! isset($points[$core])) {
                return null;
            }
            $total += $points[$core];
        }

        $others = [];
        foreach ($points as $subject => $value) {
            if ($value !== null && ! in_array($subject, $rule['core'], true) && ! in_array($subject, $rule['exclude'], true)) {
                $others[] = $value;
            }
        }
        if (count($others) < $rule['best']) {
            return null;
        }

        sort($others);

        return $total + array_sum(array_slice($others, 0, $rule['best']));
    }
}
