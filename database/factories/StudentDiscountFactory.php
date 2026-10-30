<?php

namespace Database\Factories;

use App\Models\Discount;
use App\Models\Student;
use App\Models\StudentDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the student and discount are created in that school.
 *
 * @extends Factory<StudentDiscount>
 */
class StudentDiscountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'discount_id' => fn (array $a) => Discount::factory()->create(['school_id' => $a['school_id']])->id,
        ];
    }
}
