<?php

namespace Database\Factories;

use App\Models\AdmissionSequence;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionSequence>
 */
class AdmissionSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'scope' => (string) fake()->unique()->numberBetween(2000, 2999),
            'last_number' => 0,
        ];
    }
}
