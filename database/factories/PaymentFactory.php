<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Student;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the student is created in that school.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'receipt_number' => 'T/R/'.fake()->unique()->numberBetween(1, 999_999),
            'amount' => Money::pesewas(50_000),
            'method' => PaymentMethod::Cash,
            'paid_on' => now()->toDateString(),
        ];
    }
}
