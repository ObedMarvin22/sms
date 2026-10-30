<?php

namespace Database\Factories;

use App\Enums\FeeCategory;
use App\Models\FeeItem;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeItem>
 */
class FeeItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Fee '.fake()->unique()->numberBetween(1, 99999),
            'category' => FeeCategory::Tuition,
            'is_optional' => false,
            'is_active' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(['is_optional' => true, 'category' => FeeCategory::Transport]);
    }
}
