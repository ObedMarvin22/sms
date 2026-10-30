<?php

namespace App\Actions\Students;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Admits a student. A typed-in admission number is kept (if unique); a blank one is generated
 * under the counter lock in the same transaction that saves the student.
 * (Enrolment into a stream on admission comes in Sprint 3 step 3.)
 */
class AdmitStudent
{
    public function __construct(private GenerateAdmissionNumber $generateAdmissionNumber) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Student
    {
        $data = StudentRules::details($input);

        return DB::transaction(function () use ($data): Student {
            $data['admission_number'] ??= $this->generateAdmissionNumber->handle($data['admitted_on']);

            return Student::create($data);
        }, attempts: 5); // retried if MySQL reports a deadlock (see GenerateAdmissionNumber)
    }
}
