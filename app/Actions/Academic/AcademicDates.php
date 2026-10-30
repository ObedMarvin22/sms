<?php

namespace App\Actions\Academic;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Shared date checks for academic years and terms. Errors are keyed for the form fields.
 */
final class AcademicDates
{
    public static function parse(?string $value, string $field): CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Enter a date (YYYY-MM-DD).']);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Enter a real date.']);
        }

        return $date;
    }

    /**
     * A term must sit inside its year, end after it starts, and have its census date inside it.
     */
    public static function checkTerm(
        CarbonImmutable $yearStart,
        CarbonImmutable $yearEnd,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $census,
        string $prefix,
    ): void {
        $errors = [];

        if ($end->lte($start)) {
            $errors["{$prefix}.ends_on"] = 'The end date must be after the start date.';
        }
        if ($start->lt($yearStart) || $end->gt($yearEnd)) {
            $errors["{$prefix}.starts_on"] = 'The dates must fall within the academic year.';
        }
        if ($census->lt($start) || $census->gt($end)) {
            $errors["{$prefix}.census_on"] = 'The census date must fall within the term.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Ranges (in order) must not overlap: each starts after the previous one ends.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $ranges
     */
    public static function checkInOrderWithoutOverlap(array $ranges, string $prefix): void
    {
        for ($i = 1; $i < count($ranges); $i++) {
            if ($ranges[$i][0]->lte($ranges[$i - 1][1])) {
                throw ValidationException::withMessages([
                    "{$prefix}.{$i}.starts_on" => 'This must start after the previous one ends.',
                ]);
            }
        }
    }
}
