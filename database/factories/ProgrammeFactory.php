<?php

namespace Database\Factories;

use App\Models\Programme;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Programme '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
