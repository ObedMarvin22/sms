<?php

namespace Database\Factories;

use App\Enums\ScoreStatus;
use App\Models\AssessmentScore;
use App\Models\AssessmentTask;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the task and student are created in that school.
 *
 * @extends Factory<AssessmentScore>
 */
class AssessmentScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_task_id' => fn (array $a) => AssessmentTask::factory()->create(['school_id' => $a['school_id']])->id,
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'status' => ScoreStatus::Marked,
            'mark' => '15.0',
        ];
    }
}
