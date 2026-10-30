<?php

/*
| Helpers shared by the attendance test files (loaded with require_once, so declared once).
*/

use App\Actions\Attendance\AttendanceRules;
use App\Actions\Attendance\TakeAttendance;
use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Students\AdmitStudent;
use App\Enums\AttendanceStatus;
use App\Enums\RoleKey;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;

function classTeacherOf(Stream $stream): User
{
    $user = User::factory()->for(app(CurrentSchool::class)->require())->create();
    $user->assignRole(RoleKey::ClassTeacher->value);
    $staff = Staff::factory()->for(app(CurrentSchool::class)->require())->create(['user_id' => $user->id]);
    app(AssignClassTeacher::class)->handle($stream, $staff);

    return $user;
}

function pupil(string $firstName, Stream $stream): Student
{
    return app(AdmitStudent::class)->handle(['first_name' => $firstName, 'last_name' => 'Mensah', 'stream_id' => $stream->id, 'admitted_on' => '2026-09-08']);
}

/**
 * @param  array<int, string>  $statuses  student id => status; everyone else present
 */
function register(User $user, Stream $stream, array $statuses = [], string $date = '2026-10-28', ?string $reason = null): AttendanceSession
{
    $marks = [];
    foreach (AttendanceRules::enrolmentsOn($stream, CarbonImmutable::parse($date)) as $enrolment) {
        $marks[$enrolment->student_id] = ['status' => $statuses[$enrolment->student_id] ?? 'present', 'note' => ''];
    }

    return app(TakeAttendance::class)->handle($user, $stream, $date, $marks, $reason);
}

function markOf(Student $student, string $date = '2026-10-28'): ?AttendanceStatus
{
    return AttendanceRecord::where('student_id', $student->id)
        ->whereHas('session', fn ($q) => $q->whereDate('date', $date))->first()?->status;
}
