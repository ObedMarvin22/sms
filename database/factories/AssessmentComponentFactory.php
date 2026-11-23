<?php

namespace Database\Factories;

use App\Enums\ComponentKind;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentComponent>
 */
class AssessmentComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_scheme_id' => AssessmentScheme::factory(),
            // Test data may be built while another school is current, so look up without the scope.
            'school_id' => fn (array $attributes) => AssessmentScheme::withoutGlobalScopes()->findOrFail((int) $attributes['assessment_scheme_id'])->school_id,
            'name' => 'Tests '.fake()->unique()->numberBetween(1, 99999),
            'kind' => ComponentKind::Test,
            'weight' => 30,
        ];
    }
}
