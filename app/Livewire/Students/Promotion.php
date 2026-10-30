<?php

namespace App\Livewire\Students;

use App\Actions\Students\PromoteStudents;
use App\Enums\PermissionKey;
use App\Models\AcademicYear;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Models\Stream;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * End-of-year promotion for one stream: promote, repeat or graduate each student.
 */
#[Title('Promotion')]
class Promotion extends Component
{
    public string $fromStreamId = '';

    public string $nextYearId = '';

    /** @var array<int, array{action: string, stream_id: string}> student id => decision */
    public array $decisions = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->nextYearId = (string) ($this->nextYears()->first()->id ?? '');
    }

    #[Computed]
    public function currentYear(): ?AcademicYear
    {
        return AcademicYear::current()->first();
    }

    /**
     * Years that start after the current one ends.
     *
     * @return Collection<int, AcademicYear>
     */
    public function nextYears(): Collection
    {
        $current = $this->currentYear();

        return $current === null ? new Collection
            : AcademicYear::where('starts_on', '>', $current->ends_on)->orderBy('starts_on')->get();
    }

    /**
     * @return array<int, string> stream id => "JHS 2A"
     */
    #[Computed]
    public function streamOptions(): array
    {
        return Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get(['streams.id', 'streams.name', 'school_classes.name as class_name'])
            ->mapWithKeys(fn (Stream $s) => [$s->id => $s->getAttribute('class_name').$s->name])
            ->all();
    }

    /**
     * Current enrolments in the chosen stream this year.
     *
     * @return Collection<int, Enrolment>
     */
    #[Computed]
    public function enrolments(): Collection
    {
        $year = $this->currentYear();

        return $year === null || $this->fromStreamId === '' ? new Collection
            : Enrolment::with('student')->current()
                ->where(['academic_year_id' => $year->id, 'stream_id' => (int) $this->fromStreamId])
                ->get()->sortBy(fn (Enrolment $e) => $e->student->last_name.' '.$e->student->first_name)->values();
    }

    public function updatedFromStreamId(): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        unset($this->enrolments);

        // Default: promote to the first stream of the next class; graduate from the last class.
        $from = Stream::with('schoolClass')->find((int) $this->fromStreamId);
        $nextClass = $from === null ? null
            : SchoolClass::with('streams')->where('sequence', '>', $from->schoolClass->sequence)->orderBy('sequence')->first();
        $defaultStream = (string) ($nextClass?->streams->first()->id ?? '');

        $this->decisions = [];
        foreach ($this->enrolments() as $enrolment) {
            $this->decisions[$enrolment->student_id] = $defaultStream === ''
                ? ['action' => PromoteStudents::GRADUATE, 'stream_id' => '']
                : ['action' => PromoteStudents::PROMOTE, 'stream_id' => $defaultStream];
        }
    }

    public function promote(PromoteStudents $promoteStudents): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();

        try {
            $count = $promoteStudents->handle(
                Stream::findOrFail((int) $this->fromStreamId),
                AcademicYear::findOrFail((int) $this->nextYearId),
                $this->decisions,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->decisions = [];
        unset($this->enrolments);
        Flux::toast(variant: 'success', text: __(':count students processed.', ['count' => $count]));
    }

    public function render(): View
    {
        return view('livewire.students.promotion');
    }
}
