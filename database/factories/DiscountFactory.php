<?php

namespace Database\Factories;

use App\Enums\DiscountKind;
use App\Enums\DiscountType;
use App\Models\Discount;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Discount '.fake()->unique()->numberBetween(1, 99999),
            'kind' => DiscountKind::Custom,
            'type' => DiscountType::Percent,
            'value' => 1_000,
            'fee_item_ids' => null,
            'is_active' => true,
        ];
    }
}
