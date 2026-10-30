<?php

namespace App\Actions\Students;

use App\Enums\EnrolmentStatus;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\Programme;
use App\Models\Stream;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Building blocks for the enrolment Actions. History is never overwritten: a change ends the
 * current enrolment and starts a new one. Call inside the Action's transaction.
 */
final class Enrolments
{
    /**
     * SHS streams need a programme; other levels must not have one.
     */
    public static function programmeFor(Stream $stream, ?int $programmeId): ?Programme
    {
        $isShs = $stream->schoolClass->level === SchoolLevel::Shs;

        if (! $isShs) {
            return null;
        }
        if ($programmeId === null) {
            throw ValidationException::withMessages(['programme_id' => 'Choose a programme for an SHS class.']);
        }

        return Programme::find($programmeId) ?? throw ValidationException::withMessages(['programme_id' => 'Choose one of the school\'s programmes.']);
    }

    public static function currentOf(Student $student): ?Enrolment
    {
        return Enrolment::where('student_id', $student->id)->current()->lockForUpdate()->first();
    }

    public static function end(Enrolment $enrolment, EnrolmentStatus $status, CarbonImmutable $on, ?string $reason = null): void
    {
        if ($on->lt($enrolment->starts_on)) {
            throw ValidationException::withMessages(['date' => 'The date cannot be before the enrolment started ('.$enrolment->starts_on->format('j M Y').').']);
        }

        $enrolment->forceFill([
            'status' => $status,
            'ends_on' => $on,
            'reason' => $reason ?? $enrolment->reason,
            'is_current' => false,
        ])->save();
    }

    public static function start(Student $student, AcademicYear $year, Stream $stream, ?Programme $programme, CarbonImmutable $on, ?string $reason = null): Enrolment
    {
        $enrolment = new Enrolment([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'stream_id' => $stream->id,
            'programme_id' => $programme?->id,
            'starts_on' => $on,
            'reason' => $reason,
        ]);
        $enrolment->is_current = true;
        $enrolment->save();

        return $enrolment;
    }

    public static function date(?string $value, string $field = 'date'): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::today();
        }

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;

        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Enter a real date (YYYY-MM-DD).']);
        }

        return $date;
    }
}
