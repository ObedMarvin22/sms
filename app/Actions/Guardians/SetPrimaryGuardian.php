<?php

namespace App\Actions\Guardians;

use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Makes one guardian the child's primary contact (the first number the school calls).
 */
class SetPrimaryGuardian
{
    public function handle(GuardianStudent $link): GuardianStudent
    {
        return DB::transaction(function () use ($link) {
            Student::whereKey($link->student_id)->lockForUpdate()->firstOrFail();

            // Clear the old primary first: the unique index allows only one at a time. Saved
            // one by one (not a mass update) so the change is in the activity log.
            GuardianStudent::where('student_id', $link->student_id)->where('is_primary', true)
                ->whereKeyNot($link->id)->get()
                ->each(fn (GuardianStudent $old) => $old->forceFill(['is_primary' => false])->save());

            $link->forceFill(['is_primary' => true])->save();

            return $link;
        });
    }
}
