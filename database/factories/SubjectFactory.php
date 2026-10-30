<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Subject '.fake()->unique()->numberBetween(1, 99999),
            'code' => null,
            'levels' => [SchoolLevel::Primary],
        ];
    }
}
