<?php

namespace App\Actions\ParentApp;

use App\Enums\GuardianAccess;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\LedgerEntry;
use App\Support\Money\Money;
use App\Support\ParentApp\ParentChild;

/**
 * The guardian's children with today's attendance and, where they have full access, the balance.
 * A fixed number of queries however many children there are.
 */
class ListParentChildren
{
    /**
     * @return list<ParentChild>
     */
    public function handle(Guardian $guardian, ?int $onlyStudentId = null): array
    {
        $links = FindParentChild::visibleLinks($guardian)
            ->when($onlyStudentId !== null, fn ($query) => $query->where('student_id', $onlyStudentId))
            ->with(['student.currentEnrolment.stream.schoolClass'])
            ->get()
            ->sortBy(fn (GuardianStudent $link) => $link->student->first_name)
            ->values();

        $ids = $links->pluck('student_id')->all();

        $today = AttendanceRecord::whereIn('student_id', $ids)
            ->whereHas('session', fn ($query) => $query->whereDate('date', today()))
            ->get()
            ->keyBy('student_id');

        $fullIds = $links->filter(fn (GuardianStudent $link) => $link->access_level === GuardianAccess::Full)->pluck('student_id')->all();
        $balances = LedgerEntry::whereIn('student_id', $fullIds)
            ->groupBy('student_id')
            ->selectRaw('student_id, SUM(amount) as total')
            ->pluck('total', 'student_id');

        return array_values($links->map(fn (GuardianStudent $link) => new ParentChild(
            $link,
            $today->get($link->student_id)?->status,
            in_array($link->student_id, $fullIds, true) ? Money::pesewas((int) ($balances[$link->student_id] ?? 0)) : null,
        ))->all());
    }
}
