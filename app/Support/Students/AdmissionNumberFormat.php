<?php

namespace App\Support\Students;

use App\Models\School;
use App\Support\Settings\SchoolSettings;
use Illuminate\Support\Str;

/**
 * A school's admission number format, e.g. "{PREFIX}/{YYYY}/{SEQ:4}" -> "GHS/2026/0001".
 *
 * Tokens: {PREFIX}, {YYYY}, {YY}, {SEQ:n} (number padded to n digits, exactly once).
 * If the format contains the year, numbering restarts each year (the "scope" is the year).
 */
final class AdmissionNumberFormat
{
    public const DEFAULT = '{PREFIX}/{YYYY}/{SEQ:4}';

    public function __construct(public readonly string $format, public readonly string $prefix) {}

    public static function for(School $school): self
    {
        $settings = SchoolSettings::for($school);

        return new self(
            (string) ($settings->get('admission_number_format') ?? self::DEFAULT),
            (string) ($settings->get('admission_prefix') ?? self::defaultPrefix($school->name)),
        );
    }

    /**
     * Initials of the school's name, e.g. "Greenhill Academy School" -> "GAS".
     */
    public static function defaultPrefix(string $schoolName): string
    {
        return Str::of($schoolName)->explode(' ')->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(4)->join('') ?: 'SCH';
    }

    /**
     * Null when valid, otherwise what is wrong.
     */
    public static function problem(string $format): ?string
    {
        if (preg_match_all('/\{SEQ:(\d)\}/', $format, $m) !== 1) {
            return 'Include the sequence exactly once, e.g. {SEQ:4}.';
        }
        if ((int) $m[1][0] < 1 || (int) $m[1][0] > 8) {
            return 'Pad the sequence to between 1 and 8 digits.';
        }
        // Any brace left after removing the known tokens is an unknown token.
        $rest = (string) preg_replace('/\{(PREFIX|YYYY|YY|SEQ:\d)\}/', '', $format);
        if (str_contains($rest, '{') || str_contains($rest, '}')) {
            return 'Only {PREFIX}, {YYYY}, {YY} and {SEQ:n} may be used.';
        }
        if (mb_strlen($format) > 40) {
            return 'Keep the format to 40 characters.';
        }

        return null;
    }

    /**
     * Numbering restarts per year when the year is part of the number.
     */
    public function scope(int $year): string
    {
        return str_contains($this->format, '{YYYY}') || str_contains($this->format, '{YY}') ? (string) $year : 'all';
    }

    public function render(int $year, int $sequence): string
    {
        return (string) preg_replace_callback(
            '/\{(PREFIX|YYYY|YY|SEQ:(\d))\}/',
            fn (array $m) => match (true) {
                $m[1] === 'PREFIX' => $this->prefix,
                $m[1] === 'YYYY' => (string) $year,
                $m[1] === 'YY' => substr((string) $year, -2),
                // Only {SEQ:n} reaches here, so the padding group is present.
                default => str_pad((string) $sequence, (int) ($m[2] ?? 1), '0', STR_PAD_LEFT),
            },
            $this->format,
        );
    }
}
