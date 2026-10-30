<?php

namespace App\Support\Academic;

use App\Enums\AcademicPeriodType;
use Carbon\CarbonImmutable;

/**
 * A typical Ghanaian school calendar, used to prefill a new academic year (all dates editable).
 * Census dates default to two weeks after each term starts.
 */
final class DefaultCalendar
{
    public const CENSUS_DAYS_AFTER_START = 14;

    /**
     * @return array{name: string, starts_on: string, ends_on: string, terms: list<array{starts_on: string, ends_on: string, census_on: string}>}
     */
    public static function for(int $startYear, AcademicPeriodType $period): array
    {
        $y = $startYear;
        $n = $startYear + 1;

        $ranges = match ($period) {
            AcademicPeriodType::Term => [
                ["{$y}-09-08", "{$y}-12-18"],
                ["{$n}-01-12", "{$n}-04-02"],
                ["{$n}-04-27", "{$n}-07-24"],
            ],
            AcademicPeriodType::Semester => [
                ["{$y}-09-08", "{$n}-01-29"],
                ["{$n}-02-16", "{$n}-07-24"],
            ],
        };

        return [
            'name' => "{$y}/{$n}",
            'starts_on' => "{$y}-09-01",
            'ends_on' => "{$n}-07-31",
            'terms' => array_map(fn (array $range) => [
                'starts_on' => $range[0],
                'ends_on' => $range[1],
                'census_on' => CarbonImmutable::parse($range[0])->addDays(self::CENSUS_DAYS_AFTER_START)->toDateString(),
            ], $ranges),
        ];
    }
}
