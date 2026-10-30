<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the register and enrolment are created in that school.
 *
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_session_id' => fn (array $a) => AttendanceSession::factory()->create(['school_id' => $a['school_id']])->id,
            'enrolment_id' => fn (array $a) => Enrolment::factory()->create(['school_id' => $a['school_id']])->id,
            // Test data may be built while another school is current, so no school scope here.
            'student_id' => fn (array $a) => Enrolment::withoutGlobalScopes()->whereKey($a['enrolment_id'])->value('student_id'),
            'status' => AttendanceStatus::Present,
        ];
    }
}
