<?php

namespace Database\Factories;

use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the guardian and student are created in that school.
 *
 * @extends Factory<GuardianStudent>
 */
class GuardianStudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guardian_id' => fn (array $a) => Guardian::factory()->create(['school_id' => $a['school_id']])->id,
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'relationship' => GuardianRelationship::Mother,
        ];
    }
}
