<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: every related row is created in that school.
 *
 * @extends Factory<Enrolment>
 */
class EnrolmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fn (array $a) => Student::factory()->create(['school_id' => $a['school_id']])->id,
            'academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'stream_id' => fn (array $a) => Stream::factory()->create([
                'school_id' => $a['school_id'],
                'school_class_id' => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
            'starts_on' => now()->toDateString(),
        ];
    }
}
