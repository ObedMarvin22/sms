<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'school_id' => School::factory(),
            'admission_number' => 'ADM/'.fake()->unique()->numberBetween(1, 9_999_999),
            'first_name' => fake()->firstName($gender === Gender::Female ? 'female' : 'male'),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-3 years')->format('Y-m-d'),
            'admitted_on' => now()->toDateString(),
        ];
    }
}
