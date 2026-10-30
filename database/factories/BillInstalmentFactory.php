<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\BillInstalment;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the bill is created in that school.
 *
 * @extends Factory<BillInstalment>
 */
class BillInstalmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bill_id' => fn (array $a) => Bill::factory()->create(['school_id' => $a['school_id']])->id,
            'sequence' => 1,
            'due_on' => now()->toDateString(),
            'amount' => Money::pesewas(100_000),
        ];
    }
}
