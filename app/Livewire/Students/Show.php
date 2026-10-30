<?php

namespace App\Livewire\Students;

use App\Actions\Students\MoveStudent;
use App\Actions\Students\WithdrawStudent;
use App\Enums\PermissionKey;
use App\Enums\StudentStatus;
use App\Models\Stream;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One student: details, enrolment history, and (for students.manage) move and withdraw.
 * Route model binding goes through BelongsToSchool, so another school's student is a 404.
 */
#[Title('Student')]
class Show extends Component
{
    #[Locked]
    public int $studentId;

    public string $moveStreamId = '';

    public string $moveOn = '';

    public string $moveReason = '';

    public string $leaving = 'withdrawn';

    public string $leaveOn = '';

    public string $leaveReason = '';

    public function mount(Student $student): void
    {
        $this->authorize(PermissionKey::StudentsView->value);
        $this->studentId = $student->id;
        $this->moveOn = $this->leaveOn = now()->toDateString();
    }

    public function student(): Student
    {
        return Student::with(['enrolments.stream.schoolClass', 'enrolments.academicYear', 'enrolments.programme', 'currentEnrolment.stream'])
            ->findOrFail($this->studentId);
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::StudentsManage->value) ?? false;
    }

    /**
     * Other streams in the same year to move into.
     *
     * @return array<int, string>
     */
    public function moveOptions(): array
    {
        $current = $this->student()->currentEnrolment;

        return Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->when($current, fn ($q) => $q->where('streams.id', '!=', $current->stream_id))
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get(['streams.id', 'streams.name', 'school_classes.name as class_name'])
            ->mapWithKeys(fn (Stream $s) => [$s->id => $s->getAttribute('class_name').$s->name])
            ->all();
    }

    public function move(MoveStudent $moveStudent): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();

        try {
            $moveStudent->handle($this->student(), Stream::findOrFail((int) $this->moveStreamId), null, $this->moveOn, $this->moveReason ?: null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::modal('move')->close();
        Flux::toast(variant: 'success', text: __('Student moved.'));
    }

    public function withdraw(WithdrawStudent $withdrawStudent): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();

        try {
            $withdrawStudent->handle($this->student(), StudentStatus::from($this->leaving), $this->leaveOn, $this->leaveReason ?: null);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::modal('withdraw')->close();
        Flux::toast(variant: 'success', text: __('Student record updated.'));
    }

    /**
     * @return list<StudentStatus>
     */
    public function leavingOptions(): array
    {
        return [StudentStatus::Withdrawn, StudentStatus::Transferred, StudentStatus::Graduated];
    }

    public function render(): View
    {
        return view('livewire.students.show', ['student' => $this->student()]);
    }
}
