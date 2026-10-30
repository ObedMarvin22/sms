<?php

namespace Database\Factories;

use App\Models\ReceiptSequence;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptSequence>
 */
class ReceiptSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'year' => (int) now()->year,
            'last_number' => 0,
        ];
    }
}
