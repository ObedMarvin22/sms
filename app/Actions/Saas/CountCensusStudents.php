<?php

namespace App\Actions\Saas;

use App\Enums\EnrolmentStatus;
use App\Models\Enrolment;
use App\Models\School;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;

/**
 * Active students on a census date (SPEC section 8): enrolled that day, by the register's rule
 * (started on or before it; not ended before it; a leaver counts on their last day, a student
 * moved that day counts once, in the new class). Each student counts once.
 */
class CountCensusStudents
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(School $school, CarbonImmutable $census): int
    {
        $day = $census->toDateString();

        return $this->currentSchool->runFor($school, fn (): int => Enrolment::query()
            ->whereIn('student_id', Student::select('id')) // not deleted
            ->where('starts_on', '<=', $day)
            ->where(fn ($q) => $q->whereNull('ends_on')
                ->orWhere('ends_on', '>', $day)
                ->orWhere(fn ($q) => $q->where('ends_on', $day)->where('status', '!=', EnrolmentStatus::Moved->value)))
            ->distinct()
            ->count('student_id'));
    }
}
