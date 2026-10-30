<?php

namespace App\Actions\Fees;

use App\Models\InstalmentPlan;
use App\Models\Student;
use App\Models\StudentInstalmentPlan;
use App\Models\Term;
use Illuminate\Validation\ValidationException;

/**
 * Puts a student on one of a term's instalment plans, or (null) back on the term's default.
 * Logged.
 */
class SetInstalmentPlan
{
    public function handle(Student $student, Term $term, ?InstalmentPlan $plan): void
    {
        if ($plan !== null && $plan->term_id !== $term->id) {
            throw ValidationException::withMessages(['instalment_plan' => "Choose one of {$term->name}'s plans."]);
        }

        $current = StudentInstalmentPlan::where('student_id', $student->id)->where('term_id', $term->id)->first();

        if ($plan === null) {
            $current?->delete();

            return;
        }

        ($current ?? new StudentInstalmentPlan(['student_id' => $student->id, 'term_id' => $term->id]))
            ->fill(['instalment_plan_id' => $plan->id])->save();
    }
}
