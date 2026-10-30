<?php

namespace Database\Factories;

use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the structure and fee item are created in that school.
 *
 * @extends Factory<FeeStructureItem>
 */
class FeeStructureItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fee_structure_id' => fn (array $a) => FeeStructure::factory()->create(['school_id' => $a['school_id']])->id,
            'fee_item_id' => fn (array $a) => FeeItem::factory()->create(['school_id' => $a['school_id']])->id,
            'amount' => Money::pesewas(50_000),
        ];
    }
}
