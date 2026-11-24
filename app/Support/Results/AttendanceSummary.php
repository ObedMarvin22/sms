<?php

namespace App\Support\Results;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Term;

/**
 * Days present out of days on the register in a term, for report cards. Late counts as
 * present; absent and excused do not. One query for a whole class.
 */
final class AttendanceSummary
{
    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{present: int, total: int}> student id => days
     */
    public static function for(Term $term, array $studentIds): array
    {
        $summary = array_fill_keys($studentIds, ['present' => 0, 'total' => 0]);
        if ($studentIds === []) {
            return $summary;
        }

        $rows = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.term_id', $term->id)
            ->whereIn('attendance_records.student_id', $studentIds)
            ->selectRaw('attendance_records.student_id, attendance_records.status, COUNT(*) AS n')
            ->groupBy('attendance_records.student_id', 'attendance_records.status')
            ->toBase()->get();

        foreach ($rows as $row) {
            $id = (int) $row->student_id;
            $present = in_array($row->status, [AttendanceStatus::Present->value, AttendanceStatus::Late->value], true) ? (int) $row->n : 0;
            $summary[$id] = [
                'present' => ($summary[$id]['present'] ?? 0) + $present,
                'total' => ($summary[$id]['total'] ?? 0) + (int) $row->n,
            ];
        }

        return $summary;
    }
}
