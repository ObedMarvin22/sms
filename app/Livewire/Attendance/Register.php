<?php

namespace App\Livewire\Attendance;

use App\Actions\Attendance\AttendanceRules;
use App\Actions\Attendance\TakeAttendance;
use App\Actions\Students\Enrolments;
use App\Enums\AttendanceStatus;
use App\Enums\PermissionKey;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrolment;
use App\Models\Stream;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The daily register: a class teacher's own stream (heads and administrators can open any).
 */
#[Title('Attendance')]
class Register extends Component
{
    #[Url(as: 'stream')]
    public string $streamId = '';

    #[Url]
    public string $date = '';

    /** @var array<int, array{status: string, note: string}> student id => mark */
    public array $marks = [];

    public string $reason = '';

    public function mount(): void
    {
        $user = $this->user();
        abort_unless($user->can(PermissionKey::AttendanceTake->value) || $user->can(PermissionKey::AttendanceEditLocked->value), 403);

        $streams = $this->streams();
        if ($this->streamId === '' || ! $streams->contains('id', (int) $this->streamId)) {
            $this->streamId = (string) ($streams->first()->id ?? '');
        }
        if ($this->day() === null) {
            $this->date = now()->toDateString();
        }
        $this->loadMarks();
    }

    public function updatedStreamId(): void
    {
        $this->loadMarks();
    }

    public function updatedDate(): void
    {
        $this->loadMarks();
    }

    /**
     * @return Collection<int, Stream>
     */
    public function streams(): Collection
    {
        return AttendanceRules::streamsFor($this->user());
    }

    public function selectedStream(): ?Stream
    {
        return $this->streams()->firstWhere('id', (int) $this->streamId);
    }

    public function day(): ?CarbonImmutable
    {
        try {
            return $this->date === '' ? null : Enrolments::date($this->date);
        } catch (ValidationException) {
            return null;
        }
    }

    public function dayProblem(): ?string
    {
        $day = $this->day();

        return $day === null ? 'Choose a date.' : AttendanceRules::dayProblem($day);
    }

    /**
     * @return Collection<int, Enrolment>
     */
    public function enrolments(): Collection
    {
        $stream = $this->selectedStream();
        $day = $this->day();

        return $stream === null || $day === null ? new Collection : AttendanceRules::enrolmentsOn($stream, $day);
    }

    public function session(): ?AttendanceSession
    {
        $day = $this->day();

        return $day === null ? null : AttendanceSession::with('takenBy')->where('stream_id', (int) $this->streamId)->whereDate('date', $day)->first();
    }

    public function isLocked(): bool
    {
        $day = $this->day();

        return $day !== null && AttendanceRules::isLocked(app(CurrentSchool::class)->require(), $day);
    }

    public function canEditLocked(): bool
    {
        return $this->user()->can(PermissionKey::AttendanceEditLocked->value);
    }

    public function lockTime(): string
    {
        return AttendanceRules::lockTime(app(CurrentSchool::class)->require());
    }

    public function markAll(string $status): void
    {
        $status = AttendanceStatus::from($status);
        foreach (array_keys($this->marks) as $studentId) {
            $this->marks[$studentId]['status'] = $status->value;
        }
    }

    public function save(TakeAttendance $takeAttendance): void
    {
        $this->resetErrorBag();
        $stream = $this->selectedStream() ?? abort(403);

        try {
            $takeAttendance->handle($this->user(), $stream, $this->date, $this->marks, $this->reason);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->reason = '';
        Flux::toast(variant: 'success', text: __('Register saved for :stream.', ['stream' => $stream->full_name]));
    }

    /**
     * @return list<AttendanceStatus>
     */
    public function statuses(): array
    {
        return AttendanceStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.attendance.register');
    }

    /**
     * Saved marks, or everyone present for a register not taken yet.
     */
    private function loadMarks(): void
    {
        $this->resetErrorBag();
        $session = $this->session();
        $saved = $session === null ? collect() : AttendanceRecord::where('attendance_session_id', $session->id)->get()->keyBy('student_id');

        $this->marks = [];
        foreach ($this->enrolments() as $enrolment) {
            $record = $saved->get($enrolment->student_id);
            $this->marks[$enrolment->student_id] = [
                'status' => $record->status->value ?? AttendanceStatus::Present->value,
                'note' => (string) $record?->note,
            ];
        }
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
