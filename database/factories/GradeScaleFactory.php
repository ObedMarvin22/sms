<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\GradeScale;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'level' => SchoolLevel::Primary,
            'name' => 'Primary grades',
        ];
    }
}
