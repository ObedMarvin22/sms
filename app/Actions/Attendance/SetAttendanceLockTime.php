<?php

namespace App\Actions\Attendance;

use App\Models\School;
use Illuminate\Validation\ValidationException;

/**
 * Sets the time each day's register locks (24-hour HH:MM). After it, only staff with
 * attendance.edit_locked can change a register.
 */
class SetAttendanceLockTime
{
    public function handle(School $school, string $time): School
    {
        $time = trim($time);

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw ValidationException::withMessages(['attendance_lock_time' => 'Enter a time like 10:00.']);
        }

        $school->settings = [...($school->settings ?? []), 'attendance_lock_time' => $time];
        $school->save();

        activity('schools')->performedOn($school)->event('attendance_lock_time_set')
            ->withProperties(['time' => $time])->log('attendance lock time set');

        return $school;
    }
}
