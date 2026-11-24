<?php

namespace App\Support\Academic;

/**
 * Positions with ties: equal values share a position and the next one skips (1, 2, 2, 4).
 * Values compare to two decimal places, so 68.254 and 68.25 tie.
 */
final class Ranking
{
    /**
     * @param  array<int, float|null>  $values  id => value; higher is better; nulls get no position
     * @return array<int, int> id => position
     */
    public static function positions(array $values): array
    {
        $ranked = array_filter($values, fn (?float $v) => $v !== null);
        $ranked = array_map(fn (float $v) => (int) round($v * 100), $ranked);
        arsort($ranked);

        $positions = [];
        $position = 0;
        $previous = null;
        $seen = 0;
        foreach ($ranked as $id => $value) {
            $seen++;
            if ($value !== $previous) {
                $position = $seen;
                $previous = $value;
            }
            $positions[$id] = $position;
        }

        return $positions;
    }

    /**
     * 1 → "1st", 2 → "2nd", 11 → "11th", 22 → "22nd".
     */
    public static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }
}
