<?php

namespace Database\Factories;

use App\Enums\AssessmentMode;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\AssessmentScheme;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentScheme>
 */
class AssessmentSchemeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'level' => SchoolLevel::Primary,
            'mode' => AssessmentMode::Weighted,
        ];
    }
}
