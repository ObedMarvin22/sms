<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceSession;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;

/**
 * Once today's registers have locked, notifies guardians of the current school's absent
 * students. Run every minute by the scheduler (attendance:notify-absences). Notices wait for the
 * lock so a teacher's corrections before then never reach parents.
 */
class NotifyTodaysAbsences
{
    public function __construct(private CurrentSchool $currentSchool, private NotifyAbsences $notifyAbsences) {}

    /**
     * @return int students whose guardians were notified
     */
    public function handle(): int
    {
        $today = CarbonImmutable::today();
        if (! AttendanceRules::isLocked($this->currentSchool->require(), $today)) {
            return 0;
        }

        return AttendanceSession::whereDate('date', $today)->get()
            ->sum(fn (AttendanceSession $session): int => $this->notifyAbsences->handle($session));
    }
}
