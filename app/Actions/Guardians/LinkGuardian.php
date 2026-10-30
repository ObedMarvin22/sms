<?php

namespace App\Actions\Guardians;

use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links an existing guardian to a child (e.g. a sibling). The child's first guardian becomes
 * the primary contact.
 */
class LinkGuardian
{
    /**
     * @param  array<string, mixed>  $input  relationship, access_level, can_pick_up
     */
    public function handle(Student $student, Guardian $guardian, array $input): GuardianStudent
    {
        $data = GuardianRules::link($input);

        return DB::transaction(function () use ($student, $guardian, $data) {
            // Lock the child so two links added at once cannot both become primary.
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $links = GuardianStudent::where('student_id', $student->id)->get();

            if ($links->contains('guardian_id', $guardian->id)) {
                throw ValidationException::withMessages(['phone' => "{$guardian->name} is already a guardian of this child."]);
            }

            $link = new GuardianStudent([
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
                ...$data,
            ]);
            $link->is_primary = $links->doesntContain(fn (GuardianStudent $l) => $l->is_primary);
            $link->save();

            return $link;
        });
    }
}
