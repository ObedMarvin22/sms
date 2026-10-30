<?php

namespace App\Actions\Students;

use App\Enums\EnrolmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Stream;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * End-of-year decisions for the students of one stream: promote to a stream next year,
 * repeat the same class next year, or graduate. Each ends the current enrolment (history kept)
 * and, except graduation, starts a new one on the first day of the next year.
 */
class PromoteStudents
{
    public const PROMOTE = 'promote';

    public const REPEAT = 'repeat';

    public const GRADUATE = 'graduate';

    /**
     * @param  array<int, array{action: string, stream_id?: int|string|null}>  $decisions  student id => decision
     * @return int How many students were processed.
     */
    public function handle(Stream $from, AcademicYear $nextYear, array $decisions): int
    {
        return DB::transaction(function () use ($from, $nextYear, $decisions): int {
            $count = 0;

            foreach ($decisions as $studentId => $decision) {
                $student = Student::findOrFail((int) $studentId);
                $current = Enrolments::currentOf($student);

                if ($current === null || $current->stream_id !== $from->id) {
                    throw ValidationException::withMessages(['students' => "{$student->full_name} is not currently in {$from->full_name}."]);
                }

                $fromYear = $current->academicYear;
                if ($nextYear->starts_on->lte($fromYear->ends_on)) {
                    throw ValidationException::withMessages(['year' => "Promote into a year that starts after {$fromYear->name} ends."]);
                }

                match ($decision['action']) {
                    self::PROMOTE => $this->promote($student, $current, $nextYear, $this->target($decision), EnrolmentStatus::Promoted),
                    self::REPEAT => $this->repeat($student, $current, $nextYear, $this->target($decision)),
                    self::GRADUATE => $this->graduate($student, $current),
                    default => throw ValidationException::withMessages(['students' => 'Choose promote, repeat or graduate.']),
                };

                $count++;
            }

            return $count;
        });
    }

    private function promote(Student $student, Enrolment $current, AcademicYear $nextYear, Stream $to, EnrolmentStatus $status): void
    {
        $programme = Enrolments::programmeFor($to, $current->programme_id);

        Enrolments::end($current, $status, $current->academicYear->ends_on);
        Enrolments::start($student, $nextYear, $to, $programme, $nextYear->starts_on, $status->label().' from '.$current->stream->full_name);
    }

    private function repeat(Student $student, Enrolment $current, AcademicYear $nextYear, Stream $to): void
    {
        if ($to->school_class_id !== $current->stream->school_class_id) {
            throw ValidationException::withMessages(['students' => "{$student->full_name} repeats, so stays in {$current->stream->schoolClass->name}."]);
        }

        $this->promote($student, $current, $nextYear, $to, EnrolmentStatus::Repeated);
    }

    private function graduate(Student $student, Enrolment $current): void
    {
        Enrolments::end($current, EnrolmentStatus::Graduated, $current->academicYear->ends_on);
        $student->forceFill(['status' => StudentStatus::Graduated])->save();
    }

    /**
     * @param  array{action: string, stream_id?: int|string|null}  $decision
     */
    private function target(array $decision): Stream
    {
        return Stream::with('schoolClass')->find((int) ($decision['stream_id'] ?? 0))
            ?? throw ValidationException::withMessages(['students' => 'Choose the stream for next year.']);
    }
}
