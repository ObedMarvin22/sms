<?php

namespace Database\Factories;

use App\Models\GradeBand;
use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeBand>
 */
class GradeBandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grade_scale_id' => GradeScale::factory(),
            // Test data may be built while another school is current, so look up without the scope.
            'school_id' => fn (array $a) => GradeScale::withoutGlobalScopes()->findOrFail((int) $a['grade_scale_id'])->school_id,
            'min_score' => fake()->unique()->numberBetween(0, 100),
            'grade' => fake()->unique()->bothify('?#'),
            'label' => 'Band',
            'points' => null,
        ];
    }
}
