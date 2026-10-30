<?php

namespace App\Actions\Academic;

use App\Models\AcademicYear;
use App\Models\Enrolment;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an academic year and its terms. The current year cannot be deleted.
 * A year with student enrolments cannot be deleted either.
 */
class DeleteAcademicYear
{
    public function handle(AcademicYear $year): void
    {
        // Ask the database, not the passed-in model, which may be stale.
        if (AcademicYear::whereKey($year->id)->where('is_current', true)->exists()) {
            throw ValidationException::withMessages(['year' => 'The current academic year cannot be deleted.']);
        }

        if (Enrolment::where('academic_year_id', $year->id)->exists()) {
            throw ValidationException::withMessages(['year' => "{$year->name} has student enrolments, so it cannot be deleted."]);
        }

        $year->delete();
    }
}
