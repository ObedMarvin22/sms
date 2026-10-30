<?php

namespace App\Actions\Billing;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for billing a term. Queries are school-scoped.
 */
final class TermBilling
{
    /**
     * Only the current academic year's terms are billed.
     */
    public static function assertBillable(Term $term): void
    {
        if ($term->academic_year_id !== AcademicYear::current()->value('id')) {
            throw ValidationException::withMessages(['term' => 'Only terms of the current academic year can be billed.']);
        }
    }

    /**
     * Active students enrolled in the term's year who have no bill for it yet.
     *
     * @return Builder<Student>
     */
    public static function unbilled(Term $term): Builder
    {
        return Student::query()
            ->where('status', StudentStatus::Active->value)
            ->whereHas('currentEnrolment', fn ($q) => $q->where('academic_year_id', $term->academic_year_id))
            ->whereNotIn('id', Bill::where('term_id', $term->id)->select('student_id'));
    }
}
