<?php

namespace App\Actions\ParentApp;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * A child's register marks for one month (default: this month), with a count per status.
 * Teachers' notes stay in the school: they are written for staff.
 */
class ChildAttendance
{
    /**
     * @return array{month: string, days: list<array{date: string, status: AttendanceStatus}>, counts: array<string, int>}
     */
    public function handle(Student $student, ?string $month = null): array
    {
        if ($month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw ValidationException::withMessages(['month' => 'Give the month as YYYY-MM, like 2026-10.']);
        }
        $start = $month === null ? CarbonImmutable::today()->startOfMonth() : CarbonImmutable::createFromFormat('!Y-m', $month);
        assert($start instanceof CarbonImmutable);

        $records = AttendanceRecord::with('session')
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($query) => $query->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()]))
            ->get()
            ->sortBy(fn (AttendanceRecord $record) => $record->session->date->toDateString())
            ->values();

        $counts = array_fill_keys(array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::cases()), 0);
        $days = [];
        foreach ($records as $record) {
            $counts[$record->status->value]++;
            $days[] = ['date' => $record->session->date->toDateString(), 'status' => $record->status];
        }

        return ['month' => $start->format('Y-m'), 'days' => $days, 'counts' => $counts];
    }
}
