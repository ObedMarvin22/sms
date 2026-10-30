<?php

namespace App\Actions\Staff;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Validation\ValidationException;

/**
 * Sets (or with null, removes) the teacher of a subject in a stream for a year (default: current).
 * One teacher per subject per stream per year. The class must take the subject.
 */
class AssignTeacher
{
    public function handle(Stream $stream, Subject $subject, ?Staff $staff, ?AcademicYear $year = null): ?TeachingAssignment
    {
        $year = AssignmentRules::year($year);
        AssignmentRules::activeStaff($staff);

        $classTakesSubject = ClassSubject::where('school_class_id', $stream->school_class_id)
            ->where('subject_id', $subject->id)
            ->exists();

        if (! $classTakesSubject) {
            throw ValidationException::withMessages(['subject' => "{$stream->schoolClass->name} does not take {$subject->name}. Add it under Class subjects first."]);
        }

        $existing = TeachingAssignment::where([
            'academic_year_id' => $year->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id,
        ])->first();

        if ($staff === null) {
            $existing?->delete();

            return null;
        }

        if ($existing !== null) {
            $existing->update(['staff_id' => $staff->id]);

            return $existing;
        }

        return TeachingAssignment::create([
            'academic_year_id' => $year->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id, 'staff_id' => $staff->id,
        ]);
    }
}
