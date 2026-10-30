<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'school_id' => fn (array $attributes) => self::year($attributes)->school_id,
            'sequence' => 1,
            'name' => 'First term',
            'starts_on' => fn (array $attributes) => self::year($attributes)->starts_on,
            'ends_on' => fn (array $attributes) => self::year($attributes)->starts_on->addMonths(3),
            'census_on' => fn (array $attributes) => self::year($attributes)->starts_on->addWeeks(2),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function year(array $attributes): AcademicYear
    {
        // Test data may be built while another school is current (e.g. isolation tests),
        // so look the year up without the school scope.
        return AcademicYear::withoutGlobalScopes()->findOrFail((int) $attributes['academic_year_id']);
    }
}
