<?php

namespace App\Actions\Staff;

use App\Enums\StaffStatus;
use App\Models\ClassTeacherAssignment;
use App\Models\Staff;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records that a staff member has left: their login is switched off and their teaching and
 * class-teacher assignments are removed. The record and its history are kept.
 */
class MarkStaffLeft
{
    public function handle(Staff $staff, User $actor): Staff
    {
        if ($staff->user_id !== null && $staff->user_id === $actor->id) {
            throw ValidationException::withMessages(['staff' => 'You cannot mark yourself as having left.']);
        }

        DB::transaction(function () use ($staff): void {
            $staff->forceFill(['status' => StaffStatus::Left])->save();
            $staff->user?->forceFill(['deactivated_at' => now()])->save();

            TeachingAssignment::where('staff_id', $staff->id)->get()->each->delete();
            ClassTeacherAssignment::where('staff_id', $staff->id)->get()->each->delete();
        });

        return $staff;
    }
}
