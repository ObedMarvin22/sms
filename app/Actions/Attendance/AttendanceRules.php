<?php

namespace App\Actions\Attendance;

use App\Enums\EnrolmentStatus;
use App\Enums\PermissionKey;
use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use App\Support\Settings\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who may take which register, which students are on it, and when it locks.
 * Queries are school-scoped (BelongsToSchool).
 */
final class AttendanceRules
{
    public const DEFAULT_LOCK_TIME = '10:00';

    /**
     * Streams this user may take the register for: every stream for staff who may edit locked
     * registers (heads, administrators), otherwise the streams they are class teacher of this year.
     *
     * @return Collection<int, Stream>
     */
    public static function streamsFor(User $user): Collection
    {
        $query = Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name');

        if ($user->can(PermissionKey::AttendanceEditLocked->value)) {
            return $query->get();
        }
        if (! $user->can(PermissionKey::AttendanceTake->value)) {
            return new Collection;
        }

        return $query->whereIn('streams.id', self::classTeacherStreamIds($user))->get();
    }

    public static function canTake(User $user, Stream $stream): bool
    {
        return $user->can(PermissionKey::AttendanceEditLocked->value)
            || ($user->can(PermissionKey::AttendanceTake->value) && in_array($stream->id, self::classTeacherStreamIds($user), true));
    }

    /**
     * Students on a stream's register for a day: enrolled in it on that date. A student moved on
     * day D is on the new stream's register from D, not the old one's; a leaver is still on the
     * register on their last day.
     *
     * @return Collection<int, Enrolment>
     */
    public static function enrolmentsOn(Stream $stream, CarbonImmutable $date): Collection
    {
        $day = $date->toDateString();

        return Enrolment::query()->select('enrolments.*')
            ->join('students', 'students.id', '=', 'enrolments.student_id')
            ->with('student')
            ->where('enrolments.stream_id', $stream->id)
            ->where('enrolments.starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('enrolments.ends_on')
                ->orWhere('enrolments.ends_on', '>', $day)
                ->orWhere(fn ($q) => $q->where('enrolments.ends_on', $day)->where('enrolments.status', '!=', EnrolmentStatus::Moved->value)))
            ->orderBy('students.last_name')->orderBy('students.first_name')
            ->get();
    }

    /**
     * Why a register cannot be taken on this day, or null if it can.
     */
    public static function dayProblem(CarbonImmutable $date): ?string
    {
        $term = Term::current()->first();

        return match (true) {
            $date->isAfter(CarbonImmutable::today()) => 'A register cannot be taken for a future day.',
            $date->isWeekend() => 'There is no register at weekends.',
            $term === null => 'Set a current term first.',
            $date->lt($term->starts_on) || $date->gt($term->ends_on) => "The date must fall within {$term->name} ({$term->starts_on->format('j M')} – {$term->ends_on->format('j M Y')}).",
            default => null,
        };
    }

    public static function currentTerm(): ?Term
    {
        return Term::current()->first();
    }

    /**
     * The school's lock time as HH:MM (Ghana is on UTC all year, like the app clock).
     */
    public static function lockTime(School $school): string
    {
        $time = (string) SchoolSettings::for($school)->get('attendance_lock_time', self::DEFAULT_LOCK_TIME);

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : self::DEFAULT_LOCK_TIME;
    }

    public static function locksAt(School $school, CarbonImmutable $date): CarbonImmutable
    {
        [$hour, $minute] = array_map(intval(...), explode(':', self::lockTime($school)));

        return $date->setTime($hour, $minute);
    }

    public static function isLocked(School $school, CarbonImmutable $date): bool
    {
        return CarbonImmutable::now()->gte(self::locksAt($school, $date));
    }

    /**
     * @return list<int>
     */
    public static function classTeacherStreamIds(User $user): array
    {
        $yearId = AcademicYear::current()->value('id');

        return array_values(array_map(intval(...), ClassTeacherAssignment::query()
            ->where('academic_year_id', $yearId)
            ->whereHas('staff', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('stream_id')->all()));
    }
}
