<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Student;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the student is created in that school.
 *
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'type' => LedgerEntryType::Adjustment,
            'amount' => Money::pesewas(10_000),
            'description' => 'Opening balance',
            'entry_date' => now()->toDateString(),
        ];
    }
}
