<?php

namespace App\Actions\Academic;

use App\Enums\AcademicPeriodType;
use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Validation\ValidationException;

/**
 * Chooses terms or semesters for a school. Only allowed before its first academic year.
 */
class SetAcademicPeriodType
{
    public function handle(School $school, AcademicPeriodType $type): School
    {
        if (AcademicYear::where('school_id', $school->id)->exists()) {
            throw ValidationException::withMessages([
                'academic_period' => 'This cannot change once the school has an academic year.',
            ]);
        }

        $school->settings = [...($school->settings ?? []), 'academic_period' => $type->value];
        $school->save();

        return $school;
    }
}
