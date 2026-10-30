<?php

namespace App\Livewire\Staff;

use App\Actions\Staff\AssignClassTeacher;
use App\Actions\Staff\AssignTeacher;
use App\Enums\PermissionKey;
use App\Enums\StaffStatus;
use App\Models\AcademicYear;
use App\Models\ClassTeacherAssignment;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Teaching assignments')]
class TeachingAssignments extends Component
{
    #[Url(as: 'stream')]
    public ?int $streamId = null;

    public function mount(): void
    {
        $this->authorize(PermissionKey::StaffManage->value);
        $this->streamId ??= Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')->value('streams.id');
    }

    #[Computed]
    public function year(): ?AcademicYear
    {
        return AcademicYear::current()->first();
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::with('streams')->orderBy('sequence')->get();
    }

    #[Computed]
    public function selectedStream(): ?Stream
    {
        return $this->streamId === null ? null : Stream::with('schoolClass.subjects')->find($this->streamId);
    }

    /**
     * @return Collection<int, Staff>
     */
    #[Computed]
    public function teachers(): Collection
    {
        return Staff::where('status', StaffStatus::Active)->orderBy('last_name')->orderBy('first_name')->get();
    }

    /**
     * @return array<int, int> subject id => staff id, for the selected stream and current year
     */
    #[Computed]
    public function assigned(): array
    {
        $year = $this->year();
        if ($year === null || $this->streamId === null) {
            return [];
        }

        return TeachingAssignment::where(['academic_year_id' => $year->id, 'stream_id' => $this->streamId])
            ->pluck('staff_id', 'subject_id')->all();
    }

    #[Computed]
    public function classTeacherId(): ?int
    {
        $year = $this->year();

        return $year === null || $this->streamId === null ? null
            : ClassTeacherAssignment::where(['academic_year_id' => $year->id, 'stream_id' => $this->streamId])->value('staff_id');
    }

    /**
     * Streams missing a class teacher or a subject teacher this year.
     *
     * @return list<int>
     */
    #[Computed]
    public function incompleteStreamIds(): array
    {
        $year = $this->year();
        if ($year === null) {
            return [];
        }

        $incomplete = [];
        foreach ($this->classes() as $class) {
            $subjectCount = $class->subjects()->count();
            foreach ($class->streams as $stream) {
                $teachers = TeachingAssignment::where(['academic_year_id' => $year->id, 'stream_id' => $stream->id])->count();
                $hasClassTeacher = ClassTeacherAssignment::where(['academic_year_id' => $year->id, 'stream_id' => $stream->id])->exists();
                if (! $hasClassTeacher || $teachers < $subjectCount) {
                    $incomplete[] = $stream->id;
                }
            }
        }

        return $incomplete;
    }

    public function selectStream(int $streamId): void
    {
        $this->authorize(PermissionKey::StaffManage->value);
        $this->streamId = $streamId;
        unset($this->selectedStream, $this->assigned, $this->classTeacherId);
    }

    public function assignTeacher(int $subjectId, string $staffId, AssignTeacher $assignTeacher): void
    {
        $this->authorize(PermissionKey::StaffManage->value);

        $this->attempt(fn () => $assignTeacher->handle(
            Stream::findOrFail($this->streamId),
            Subject::findOrFail($subjectId),
            $staffId === '' ? null : Staff::findOrFail((int) $staffId),
        ));
    }

    public function assignClassTeacher(string $staffId, AssignClassTeacher $assignClassTeacher): void
    {
        $this->authorize(PermissionKey::StaffManage->value);

        $this->attempt(fn () => $assignClassTeacher->handle(
            Stream::findOrFail($this->streamId),
            $staffId === '' ? null : Staff::findOrFail((int) $staffId),
        ));
    }

    public function render(): View
    {
        return view('livewire.staff.teaching-assignments');
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
            unset($this->assigned, $this->classTeacherId, $this->incompleteStreamIds);
            Flux::toast(variant: 'success', text: __('Saved.'));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());
        }
    }
}
