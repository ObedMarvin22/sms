<?php

namespace App\Actions\Academic;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Makes a term (and its year) the school's current one, clearing the previous ones.
 *
 * Runs in one transaction with the current rows locked. The unique (school_id, is_current)
 * indexes guarantee there can never be two current terms or years, even under a race.
 */
class SetCurrentTerm
{
    public function handle(Term $term): Term
    {
        return DB::transaction(function () use ($term): Term {
            // Clear the old ones first: the unique index would reject a second "true" row.
            // forceFill: is_current is deliberately not mass-assignable (update() would skip it).
            foreach (Term::current()->lockForUpdate()->get() as $previous) {
                if ($previous->isNot($term)) {
                    $previous->forceFill(['is_current' => false])->save();
                }
            }
            foreach (AcademicYear::current()->lockForUpdate()->get() as $previous) {
                if ($previous->id !== $term->academic_year_id) {
                    $previous->forceFill(['is_current' => false])->save();
                }
            }

            $term->academicYear->forceFill(['is_current' => true])->save();
            $term->forceFill(['is_current' => true])->save();

            return $term;
        });
    }
}
