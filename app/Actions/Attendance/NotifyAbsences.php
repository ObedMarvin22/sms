<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\GuardianAccess;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Notifications\StudentAbsent;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the guardians of each student marked absent on a register, once per student per day.
 * Guardians with no app access (contact only) are skipped. Safe to run again: records are
 * locked and stamped with absence_notified_at.
 */
class NotifyAbsences
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @return int students whose guardians were notified
     */
    public function handle(AttendanceSession $session): int
    {
        $school = $this->currentSchool->require();

        return DB::transaction(function () use ($session, $school): int {
            $records = AttendanceRecord::with('student')
                ->where('attendance_session_id', $session->id)
                ->where('status', AttendanceStatus::Absent->value)
                ->whereNull('absence_notified_at')
                ->lockForUpdate()
                ->get();

            $notified = 0;
            foreach ($records as $record) {
                $guardians = Guardian::whereHas('links', fn ($q) => $q->where('student_id', $record->student_id)
                    ->where('access_level', '!=', GuardianAccess::None->value))->get();

                // No one to tell yet: left unstamped, so a guardian added later today still hears.
                if ($guardians->isEmpty()) {
                    continue;
                }

                Notification::send($guardians, new StudentAbsent($record, $session->date, $school));
                $record->forceFill(['absence_notified_at' => now()])->save();
                $notified++;
            }

            return $notified;
        });
    }
}
