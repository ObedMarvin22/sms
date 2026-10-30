<?php

namespace App\Actions\Attendance;

use App\Actions\Students\Enrolments;
use App\Enums\AttendanceStatus;
use App\Enums\PermissionKey;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Stream;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a stream's register for a day: every student enrolled in the stream that day gets a mark.
 * Saving again corrects it. After the school's lock time only staff with attendance.edit_locked
 * may change it, with a reason, and each change is written to the activity log.
 */
class TakeAttendance
{
    public function __construct(private CurrentSchool $currentSchool, private NotifyAbsences $notifyAbsences) {}

    /**
     * @param  array<int|string, array{status?: string, note?: string|null}>  $marks  student id => mark
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Stream $stream, string $date, array $marks, ?string $reason = null): AttendanceSession
    {
        if (! AttendanceRules::canTake($user, $stream)) {
            throw new AuthorizationException('Only the class teacher can take this register.');
        }

        $day = Enrolments::date($date === '' ? null : $date, 'date');
        if (($problem = AttendanceRules::dayProblem($day)) !== null) {
            throw ValidationException::withMessages(['date' => $problem]);
        }

        $school = $this->currentSchool->require();
        $locked = AttendanceRules::isLocked($school, $day);
        if ($locked && ! $user->can(PermissionKey::AttendanceEditLocked->value)) {
            throw ValidationException::withMessages(['date' => 'This register locked at '.AttendanceRules::lockTime($school).'. Ask an administrator to change it.']);
        }

        $enrolments = AttendanceRules::enrolmentsOn($stream, $day);
        $rows = [];
        $errors = [];
        foreach ($enrolments as $enrolment) {
            $mark = $marks[$enrolment->student_id] ?? [];
            $status = AttendanceStatus::tryFrom((string) ($mark['status'] ?? ''));
            $note = trim((string) ($mark['note'] ?? ''));

            if ($status === null) {
                $errors["marks.{$enrolment->student_id}"] = "Mark {$enrolment->student->full_name} present, late, absent or excused.";
            } elseif (mb_strlen($note) > 255) {
                $errors["marks.{$enrolment->student_id}"] = 'Keep the note under 255 characters.';
            } else {
                $rows[$enrolment->student_id] = ['enrolment_id' => $enrolment->id, 'status' => $status, 'note' => $note === '' ? null : $note];
            }
        }
        if (array_diff(array_map(intval(...), array_keys($marks)), $enrolments->pluck('student_id')->all()) !== []) {
            $errors['marks'] = 'Some students are not in this stream on this day. Reload the register.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $reason = trim((string) $reason);

        $session = DB::transaction(function () use ($user, $stream, $day, $rows, $locked, $reason): AttendanceSession {
            $session = $this->session($stream, $day, $user);
            $existing = AttendanceRecord::where('attendance_session_id', $session->id)->get()->keyBy('student_id');

            $changes = [];
            foreach ($rows as $studentId => $row) {
                $record = $existing->get($studentId);
                $old = $record?->status;

                if ($record === null) {
                    $record = new AttendanceRecord(['attendance_session_id' => $session->id, 'student_id' => $studentId, ...$row]);
                } else {
                    $record->fill($row);
                }

                if ($record->isDirty()) {
                    $record->save();
                    $changes[] = [$record, $old];
                }
            }

            if ($locked && $changes !== []) {
                if ($reason === '') {
                    throw ValidationException::withMessages(['reason' => 'Give a reason for changing a locked register.']);
                }
                foreach ($changes as [$record, $old]) {
                    activity('attendance')->performedOn($record)->causedBy($user)->event('edited_after_lock')
                        ->withProperties([
                            'date' => $day->toDateString(),
                            'stream_id' => $stream->id,
                            'student_id' => $record->student_id,
                            'old' => $old?->value,
                            'new' => $record->status->value,
                            'reason' => mb_substr($reason, 0, 255),
                        ])
                        ->log('attendance changed after lock');
                }
            }

            return $session;
        });

        // Today's notices went out at the lock; an absence marked after it is notified now.
        // Earlier days are never notified.
        if ($locked && $day->isToday()) {
            $this->notifyAbsences->handle($session);
        }

        return $session;
    }

    /**
     * The day's register for the stream, created on first save. The stream row is locked first,
     * so two saves of the same register (teacher and administrator at once) run one after the
     * other and never both create it. The unique index is the last safety net.
     */
    private function session(Stream $stream, CarbonImmutable $day, User $user): AttendanceSession
    {
        Stream::whereKey($stream->id)->lockForUpdate()->firstOrFail();

        $session = AttendanceSession::where('stream_id', $stream->id)->whereDate('date', $day)->first();
        if ($session !== null) {
            return $session;
        }

        $term = AttendanceRules::currentTerm() ?? throw ValidationException::withMessages(['date' => 'Set a current term first.']);
        $session = new AttendanceSession([
            'stream_id' => $stream->id,
            'academic_year_id' => $term->academic_year_id,
            'term_id' => $term->id,
            'date' => $day,
        ]);
        $session->forceFill(['taken_by' => $user->id, 'taken_at' => now()])->save();

        return $session;
    }
}
