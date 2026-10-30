<?php

namespace App\Actions\Fees;

use App\Enums\Residence;
use App\Models\Student;

/**
 * Makes a student a day student or a boarder (logged on the student). Takes effect from the
 * next bill; bills already issued keep the residence they were billed at.
 */
class SetResidence
{
    public function handle(Student $student, Residence $residence): Student
    {
        if ($residence === Residence::Boarding) {
            FeeRules::requireBoarding();
        }

        $student->forceFill(['residence' => $residence])->save();

        return $student;
    }
}
