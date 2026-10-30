<?php

namespace App\Livewire\Attendance;

use App\Actions\Attendance\AttendanceRules;
use App\Enums\AttendanceStatus;
use App\Enums\PermissionKey;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Stream;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * For heads and administrators: which streams have taken today's register, with counts.
 */
#[Title("Today's attendance")]
class Today extends Component
{
    public function mount(): void
    {
        $this->authorize(PermissionKey::AttendanceEditLocked->value);
    }

    /**
     * @return list<array{stream: Stream, session: ?AttendanceSession, present: int, absent: int, total: int}>
     */
    public function rows(): array
    {
        $today = CarbonImmutable::today();
        $sessions = AttendanceSession::with('takenBy')->whereDate('date', $today)->get()->keyBy('stream_id');

        // One grouped query for every stream's counts.
        $counts = AttendanceRecord::query()
            ->whereIn('attendance_session_id', $sessions->pluck('id'))
            ->selectRaw('attendance_session_id, status, count(*) as total')
            ->groupBy('attendance_session_id', 'status')->get()
            ->groupBy('attendance_session_id');

        $rows = [];
        foreach (Stream::query()->select('streams.*')->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')->orderBy('school_classes.sequence')->orderBy('streams.name')->get() as $stream) {
            $session = $sessions->get($stream->id);
            $byStatus = $session === null ? collect() : ($counts->get($session->id) ?? collect())
                ->mapWithKeys(fn (AttendanceRecord $r) => [$r->status->value => (int) $r->getAttribute('total')]);

            $present = (int) $byStatus->filter(fn (int $n, string $status) => AttendanceStatus::from($status)->isPresent())->sum();
            $total = (int) $byStatus->sum();
            $rows[] = ['stream' => $stream, 'session' => $session, 'present' => $present, 'absent' => $total - $present, 'total' => $total];
        }

        return $rows;
    }

    public function dayProblem(): ?string
    {
        return AttendanceRules::dayProblem(CarbonImmutable::today());
    }

    public function render(): View
    {
        return view('livewire.attendance.today');
    }
}
