<?php

namespace App\Actions\Students;

use App\Actions\Attendance\AttendanceRules;
use App\Enums\Gender;
use App\Models\Enrolment;
use App\Models\GuardianStudent;
use App\Models\Stream;
use Carbon\CarbonImmutable;

/**
 * The students in a stream on a day, with their primary guardian, for the class list screen
 * and its PDF. Uses the register's rule for who is in a stream, so both always agree.
 */
class BuildClassList
{
    /**
     * @return array{
     *     rows: list<array{admission_number: string, name: string, gender: Gender|null, date_of_birth: CarbonImmutable|null, guardian: string|null, guardian_phone: string|null}>,
     *     boys: int, girls: int, total: int
     * }
     */
    public function handle(Stream $stream, ?CarbonImmutable $date = null): array
    {
        $enrolments = AttendanceRules::enrolmentsOn($stream, $date ?? CarbonImmutable::today());

        $primaries = GuardianStudent::with('guardian')
            ->whereIn('student_id', $enrolments->pluck('student_id'))
            ->where('is_primary', true)
            ->get()->keyBy('student_id');

        $rows = array_values($enrolments->map(function (Enrolment $enrolment) use ($primaries): array {
            $student = $enrolment->student;
            $link = $primaries->get($student->id);

            return [
                'admission_number' => $student->admission_number,
                'name' => $student->full_name,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth,
                'guardian' => $link?->guardian->name,
                'guardian_phone' => $link?->guardian->phone,
            ];
        })->all());

        return [
            'rows' => $rows,
            'boys' => count(array_filter($rows, fn (array $row): bool => $row['gender'] === Gender::Male)),
            'girls' => count(array_filter($rows, fn (array $row): bool => $row['gender'] === Gender::Female)),
            'total' => count($rows),
        ];
    }
}
