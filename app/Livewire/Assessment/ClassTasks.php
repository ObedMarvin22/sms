<?php

namespace App\Livewire\Assessment;

use App\Actions\Assessment\CreateAssessmentTask;
use App\Actions\Assessment\DeleteAssessmentTask;
use App\Actions\Assessment\TaskRules;
use App\Actions\Assessment\UpdateAssessmentTask;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Models\AssessmentTask;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One class and subject in the current term: the scheme's parts and the tasks set under each.
 *
 * @property-read Term $term
 * @property-read bool $canManage
 * @property-read AssessmentScheme|null $scheme
 * @property-read Collection<int, AssessmentTask> $tasks
 */
class ClassTasks extends Component
{
    #[Locked]
    public int $streamId;

    #[Locked]
    public int $subjectId;

    #[Locked]
    public ?int $editingId = null;

    public string $componentId = '';

    public string $name = '';

    public string $givenOn = '';

    public string $maxMark = '';

    public function mount(Stream $stream, Subject $subject): void
    {
        $term = Term::current()->firstOrFail();
        abort_unless(TaskRules::canView(auth()->user(), $stream, $subject, $term->academic_year_id), 403);

        $this->streamId = $stream->id;
        $this->subjectId = $subject->id;
    }

    #[Computed]
    public function term(): Term
    {
        return Term::current()->firstOrFail();
    }

    #[Computed]
    public function classStream(): Stream
    {
        return Stream::with('schoolClass')->findOrFail($this->streamId);
    }

    #[Computed]
    public function subject(): Subject
    {
        return Subject::findOrFail($this->subjectId);
    }

    #[Computed]
    public function canManage(): bool
    {
        return TaskRules::canManage(auth()->user(), $this->classStream(), $this->subject(), $this->term->academic_year_id);
    }

    #[Computed]
    public function scheme(): ?AssessmentScheme
    {
        return TaskRules::schemeFor($this->classStream(), $this->term->academic_year_id);
    }

    /**
     * @return Collection<int, AssessmentTask>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return AssessmentTask::where(['term_id' => $this->term->id, 'stream_id' => $this->streamId, 'subject_id' => $this->subjectId])
            ->orderBy('given_on')->orderBy('id')->get();
    }

    public function startCreate(int $componentId): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->componentId = (string) $this->component($componentId)->id;
        $this->name = '';
        $today = CarbonImmutable::today();
        $date = match (true) {
            $today->lt($this->term->starts_on) => $this->term->starts_on,
            $today->gt($this->term->ends_on) => $this->term->ends_on,
            default => $today,
        };
        $this->givenOn = $date->toDateString();
        $this->maxMark = '';

        Flux::modal('task')->show();
    }

    public function startEdit(int $taskId): void
    {
        $this->resetErrorBag();
        $task = $this->task($taskId);
        $this->editingId = $task->id;
        $this->componentId = (string) $task->assessment_component_id;
        $this->name = $task->name;
        $this->givenOn = $task->given_on->toDateString();
        $this->maxMark = (string) $task->max_mark;

        Flux::modal('task')->show();
    }

    public function save(CreateAssessmentTask $create, UpdateAssessmentTask $update): void
    {
        $this->resetErrorBag();
        $data = ['name' => $this->name, 'given_on' => $this->givenOn, 'max_mark' => $this->maxMark];

        try {
            $component = $this->component((int) $this->componentId);
            $this->editingId === null
                ? $create->handle(auth()->user(), $this->term, $this->classStream(), $this->subject(), $component, $data)
                : $update->handle(auth()->user(), $this->task($this->editingId), $component, $data);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->tasks);
        Flux::modal('task')->close();
        Flux::toast(variant: 'success', text: $this->editingId === null ? __('Task added.') : __('Task saved.'));
    }

    public function delete(int $taskId, DeleteAssessmentTask $delete): void
    {
        try {
            $delete->handle(auth()->user(), $this->task($taskId));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }
        unset($this->tasks);
        Flux::toast(text: __('Task deleted.'));
    }

    public function render(): View
    {
        return view('livewire.assessment.class-tasks')
            ->title(__(':subject, :stream', ['subject' => $this->subject()->name, 'stream' => $this->classStream()->full_name]));
    }

    private function component(int $id): AssessmentComponent
    {
        $component = $this->scheme?->components->firstWhere('id', $id);

        if ($component === null) {
            throw ValidationException::withMessages(['component' => __('Choose a part of the assessment.')]);
        }

        return $component;
    }

    private function task(int $id): AssessmentTask
    {
        return $this->tasks->firstWhere('id', $id) ?? abort(404);
    }
}
