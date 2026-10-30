<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'school_id' => School::factory(),
            'level' => SchoolLevel::Primary,
            'name' => "Class {$number}",
            'sequence' => $number,
        ];
    }
}
