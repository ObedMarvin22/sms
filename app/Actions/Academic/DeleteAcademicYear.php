<?php

namespace App\Actions\Academic;

use App\Models\AcademicYear;
use App\Models\AssessmentScheme;
use App\Models\AssessmentTask;
use App\Models\Enrolment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an academic year and its terms. The current year cannot be deleted.
 * A year with student enrolments cannot be deleted either. Its assessment schemes go with it.
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

        if (AssessmentTask::whereIn('term_id', $year->terms()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['year' => "{$year->name} has assessment tasks, so it cannot be deleted."]);
        }

        DB::transaction(function () use ($year): void {
            AssessmentScheme::with('components')->where('academic_year_id', $year->id)->get()
                ->each(function (AssessmentScheme $scheme): void {
                    $scheme->components->each->delete();
                    $scheme->delete();
                });

            $year->delete();
        });
    }
}
