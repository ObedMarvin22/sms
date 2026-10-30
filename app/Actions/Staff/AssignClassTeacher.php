<?php

namespace App\Actions\Staff;

use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\Staff;
use App\Models\Stream;

/**
 * Sets (or with null, removes) a stream's class teacher for a year (default: current).
 */
class AssignClassTeacher
{
    public function handle(Stream $stream, ?Staff $staff, ?AcademicYear $year = null): ?ClassTeacherAssignment
    {
        $year = AssignmentRules::year($year);
        AssignmentRules::activeStaff($staff);

        $existing = ClassTeacherAssignment::where(['academic_year_id' => $year->id, 'stream_id' => $stream->id])->first();

        if ($staff === null) {
            $existing?->delete();

            return null;
        }

        if ($existing !== null) {
            $existing->update(['staff_id' => $staff->id]);

            return $existing;
        }

        return ClassTeacherAssignment::create([
            'academic_year_id' => $year->id, 'stream_id' => $stream->id, 'staff_id' => $staff->id,
        ]);
    }
}
