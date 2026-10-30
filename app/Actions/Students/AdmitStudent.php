<?php

namespace App\Actions\Students;

use App\Models\AcademicYear;
use App\Models\Stream;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admits a student and enrols them into a stream for the current academic year.
 *
 * A typed-in admission number is kept (if unique); a blank one is generated under the counter
 * lock in the same transaction that saves the student.
 */
class AdmitStudent
{
    public function __construct(private GenerateAdmissionNumber $generateAdmissionNumber) {}

    /**
     * @param  array<string, mixed>  $input  student details plus stream_id and (SHS) programme_id
     */
    public function handle(array $input): Student
    {
        $data = StudentRules::details($input);

        $year = AcademicYear::current()->first()
            ?? throw ValidationException::withMessages(['stream_id' => 'Set a current academic year first.']);
        $stream = Stream::with('schoolClass')->find((int) ($input['stream_id'] ?? 0))
            ?? throw ValidationException::withMessages(['stream_id' => 'Choose the class and stream to admit into.']);
        $programme = Enrolments::programmeFor($stream, ($input['programme_id'] ?? '') === '' ? null : (int) $input['programme_id']);

        if ($data['admitted_on']->lt($year->starts_on) || $data['admitted_on']->gt($year->ends_on)) {
            throw ValidationException::withMessages(['admitted_on' => "The admission date must fall within {$year->name}."]);
        }

        return DB::transaction(function () use ($data, $year, $stream, $programme): Student {
            $data['admission_number'] ??= $this->generateAdmissionNumber->handle($data['admitted_on']);
            $student = Student::create($data);

            Enrolments::start($student, $year, $stream, $programme, $data['admitted_on'], 'Admitted');

            return $student;
        }, attempts: 5); // retried if MySQL reports a deadlock (see GenerateAdmissionNumber)
    }
}
