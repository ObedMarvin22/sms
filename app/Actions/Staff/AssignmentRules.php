<?php

namespace App\Actions\Staff;

use App\Models\AcademicYear;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

/**
 * Checks shared by the teaching and class-teacher assignment Actions.
 */
final class AssignmentRules
{
    public static function year(?AcademicYear $year): AcademicYear
    {
        return $year ?? AcademicYear::current()->first()
            ?? throw ValidationException::withMessages(['year' => 'Set a current academic year first.']);
    }

    public static function activeStaff(?Staff $staff): void
    {
        if ($staff !== null && ! $staff->isActive()) {
            throw ValidationException::withMessages(['staff' => "{$staff->full_name} has left and cannot be assigned."]);
        }
    }
}
