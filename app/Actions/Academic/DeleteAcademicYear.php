<?php

namespace App\Actions\Academic;

use App\Models\AcademicYear;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an academic year and its terms. The current year cannot be deleted.
 * (From Sprint 3, enrolments will also block deleting a year that is in use.)
 */
class DeleteAcademicYear
{
    public function handle(AcademicYear $year): void
    {
        // Ask the database, not the passed-in model, which may be stale.
        if (AcademicYear::whereKey($year->id)->where('is_current', true)->exists()) {
            throw ValidationException::withMessages(['year' => 'The current academic year cannot be deleted.']);
        }

        $year->delete();
    }
}
