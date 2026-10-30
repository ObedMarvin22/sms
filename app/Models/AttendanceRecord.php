<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's mark on a register. Changes after the lock are logged by TakeAttendance (with
 * the reason), not by LogsActivity: logging every morning's marks would flood the log.
 *
 * @property int $id
 * @property int $attendance_session_id
 * @property int $enrolment_id
 * @property int $student_id
 * @property AttendanceStatus $status
 * @property string|null $note
 * @property CarbonImmutable|null $absence_notified_at
 * @property-read Student $student
 * @property-read AttendanceSession $session
 */
#[Fillable(['attendance_session_id', 'enrolment_id', 'student_id', 'status', 'note'])]
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AttendanceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class, 'absence_notified_at' => 'immutable_datetime'];
    }
}
