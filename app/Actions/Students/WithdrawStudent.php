<?php

namespace App\Actions\Students;

use App\Enums\EnrolmentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A student leaves the school: withdrawn, transferred to another school, or graduated.
 * The current enrolment ends with that status; the record and history are kept.
 */
class WithdrawStudent
{
    public function handle(Student $student, StudentStatus $leaving, ?string $on = null, ?string $reason = null): Student
    {
        if ($leaving === StudentStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Choose why the student is leaving.']);
        }

        $date = Enrolments::date($on);
        $enrolmentStatus = match ($leaving) {
            StudentStatus::Withdrawn => EnrolmentStatus::Withdrawn,
            StudentStatus::Transferred => EnrolmentStatus::TransferredOut,
            StudentStatus::Graduated => EnrolmentStatus::Graduated,
        };

        return DB::transaction(function () use ($student, $leaving, $date, $reason, $enrolmentStatus): Student {
            $current = Enrolments::currentOf($student);

            if ($current === null || $student->status !== StudentStatus::Active) {
                throw ValidationException::withMessages(['status' => 'This student is not currently enrolled.']);
            }

            Enrolments::end($current, $enrolmentStatus, $date, $reason);
            $student->forceFill(['status' => $leaving])->save();

            return $student;
        });
    }
}
