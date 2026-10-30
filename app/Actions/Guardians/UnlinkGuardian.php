<?php

namespace App\Actions\Guardians;

use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a guardian from a child. The guardian record stays (they may have other children).
 * A child always keeps at least one guardian, and the next one becomes primary if needed.
 */
class UnlinkGuardian
{
    public function handle(GuardianStudent $link): void
    {
        DB::transaction(function () use ($link) {
            Student::whereKey($link->student_id)->lockForUpdate()->firstOrFail();

            $others = GuardianStudent::where('student_id', $link->student_id)->whereKeyNot($link->id)->orderBy('id')->get();

            if ($others->isEmpty()) {
                throw ValidationException::withMessages(['guardian' => 'A child must keep at least one guardian. Add another before removing this one.']);
            }

            $wasPrimary = $link->is_primary;
            $link->delete();

            if ($wasPrimary) {
                $others->firstOrFail()->forceFill(['is_primary' => true])->save();
            }
        });
    }
}
