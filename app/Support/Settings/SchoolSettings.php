<?php

namespace App\Support\Settings;

use App\Enums\AcademicPeriodType;
use App\Models\School;

/**
 * Typed access to schools.settings (JSON) with defaults.
 *
 * Each feature adds its keys here when it is built, with a default, so a school that never
 * saved a value still behaves sensibly. Unknown keys in the JSON are kept untouched.
 */
final class SchoolSettings
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [
        // Terms (3 per year) or semesters (2). Fixed once the school has an academic year.
        'academic_period' => 'term',
        // ISO date-time when the setup wizard was first completed (null until then).
        'setup_completed_at' => null,
        // Daily registers lock at this time (HH:MM); see App\Actions\Attendance\AttendanceRules.
        'attendance_lock_time' => '10:00',
        // Takes boarders: shows boarding fee structures and the residence switch (App\Actions\Fees).
        'offers_boarding' => false,
    ];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private array $values) {}

    public static function for(School $school): self
    {
        return new self(array_replace(self::DEFAULTS, $school->settings ?? []));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function academicPeriod(): AcademicPeriodType
    {
        return AcademicPeriodType::tryFrom((string) $this->get('academic_period')) ?? AcademicPeriodType::Term;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
