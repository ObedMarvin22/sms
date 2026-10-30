<?php

namespace Database\Factories;

use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Pass school_id: the student is created in that school.
 *
 * @extends Factory<PaystackTransaction>
 */
class PaystackTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'reference' => 'OT-TEST-'.Str::ulid(),
            'amount' => Money::pesewas(50_000),
            'charge' => Money::zero(),
            'total' => Money::pesewas(50_000),
            'email' => 'parent@example.test',
        ];
    }
}
