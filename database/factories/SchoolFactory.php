<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $place = fake()->randomElement([
            'Greenhill', 'Hillview', 'Adenta', 'Tema', 'Kasoa', 'Achimota', 'Dansoman',
            'Kumasi', 'Takoradi', 'Ho', 'Cape Coast', 'Tamale', 'Koforidua', 'Sunyani',
        ]);
        $kind = fake()->randomElement(['Academy', 'International School', 'Preparatory School', 'Montessori School']);

        return [
            'name' => "{$place} {$kind}",
            'subdomain' => Str::slug($place).'-'.fake()->unique()->numberBetween(100, 99999),
            'status' => SchoolStatus::Trial,
            'primary_colour' => fake()->hexColor(),
            'secondary_colour' => fake()->hexColor(),
            'levels_offered' => [SchoolLevel::Primary, SchoolLevel::Jhs],
            'settings' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SchoolStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SchoolStatus::Suspended,
        ]);
    }

    public function withLevels(SchoolLevel ...$levels): static
    {
        return $this->state(fn (array $attributes) => [
            'levels_offered' => $levels,
        ]);
    }
}
