<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\AssessmentComment;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the term, stream, subject and student are created in that school.
 *
 * @extends Factory<AssessmentComment>
 */
class AssessmentCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id' => fn (array $a) => Term::factory()->create([
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
            'stream_id' => fn (array $a) => Stream::factory()->create([
                'school_class_id' => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
            'subject_id' => fn (array $a) => Subject::factory()->create(['school_id' => $a['school_id']])->id,
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'comment' => 'Counts confidently to 20 and enjoys number songs.',
        ];
    }
}
