<?php

namespace Database\Factories;

use App\Models\FeeItem;
use App\Models\Student;
use App\Models\StudentFeeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the student and optional fee item are created in that school.
 *
 * @extends Factory<StudentFeeOption>
 */
class StudentFeeOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'fee_item_id' => fn (array $a) => FeeItem::factory()->optional()->create(['school_id' => $a['school_id']])->id,
        ];
    }
}
