<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: every related row is created in that school.
 *
 * @extends Factory<ClassTeacherAssignment>
 */
class ClassTeacherAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => fn (array $a) => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            'stream_id' => fn (array $a) => Stream::factory()->create([
                'school_id' => $a['school_id'],
                'school_class_id' => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
            'staff_id' => fn (array $a) => Staff::factory()->create(['school_id' => $a['school_id']])->id,
        ];
    }
}
