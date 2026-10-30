<?php

namespace Database\Factories;

use App\Models\InstalmentPlan;
use App\Models\Student;
use App\Models\StudentInstalmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the student and plan are created in that school.
 *
 * @extends Factory<StudentInstalmentPlan>
 */
class StudentInstalmentPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'instalment_plan_id' => fn (array $a) => InstalmentPlan::factory()->create(['school_id' => $a['school_id']])->id,
            'term_id' => fn (array $a) => InstalmentPlan::withoutGlobalScopes()->whereKey((int) $a['instalment_plan_id'])->value('term_id'), // plan may be built while another school is current
        ];
    }
}
