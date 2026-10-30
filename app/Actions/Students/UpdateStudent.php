<?php

namespace App\Actions\Students;

use App\Models\Student;
use Illuminate\Validation\ValidationException;

/**
 * Edits a student's details. The admission number can be corrected but not removed.
 */
class UpdateStudent
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Student $student, array $input): Student
    {
        $data = StudentRules::details($input, ignore: $student);

        if ($data['admission_number'] === null) {
            throw ValidationException::withMessages(['admission_number' => 'A student must keep an admission number.']);
        }

        $student->update($data);

        return $student;
    }
}
