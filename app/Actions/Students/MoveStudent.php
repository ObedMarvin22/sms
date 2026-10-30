<?php

namespace App\Actions\Students;

use App\Enums\EnrolmentStatus;
use App\Models\Enrolment;
use App\Models\Stream;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a student to another stream in the same academic year (e.g. JHS 2A to JHS 2B).
 * The current enrolment ends as "moved" and a new one starts.
 */
class MoveStudent
{
    public function handle(Student $student, Stream $to, ?int $programmeId = null, ?string $on = null, ?string $reason = null): Enrolment
    {
        $date = Enrolments::date($on);

        return DB::transaction(function () use ($student, $to, $programmeId, $date, $reason): Enrolment {
            $current = Enrolments::currentOf($student)
                ?? throw ValidationException::withMessages(['stream_id' => 'This student is not currently enrolled.']);

            if ($current->stream_id === $to->id) {
                throw ValidationException::withMessages(['stream_id' => 'The student is already in this stream.']);
            }

            $programme = Enrolments::programmeFor($to, $programmeId ?? $current->programme_id);

            Enrolments::end($current, EnrolmentStatus::Moved, $date, $reason);

            return Enrolments::start($student, $current->academicYear, $to, $programme, $date, $reason ?? 'Moved from '.$current->stream->full_name);
        });
    }
}
