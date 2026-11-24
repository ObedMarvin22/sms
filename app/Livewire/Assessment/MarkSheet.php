<?php

namespace App\Livewire\Assessment;

use App\Actions\Assessment\BuildScoreSheet;
use App\Actions\Assessment\SaveScore;
use App\Actions\Assessment\TaskRules;
use App\Livewire\Assessment\Concerns\ManagesSheetLock;
use App\Models\AssessmentSheet;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Assessment\ScoreSheet;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The mark sheet for one class and subject this term: students down, tasks across. Each cell
 * saves when the teacher leaves it. Class teachers of the stream see it without changing it.
 *
 * @property-read Term $term
 * @property-read bool $canManage
 * @property-read ScoreSheet $sheet
 * @property-read bool $editable
 * @property-read bool $locked
 * @property-read bool $canUnlock
 * @property-read AssessmentSheet|null $sheetRecord
 * @property-read list<string> $problems
 */
class MarkSheet extends Component
{
    use ManagesSheetLock;

    #[Locked]
    public int $streamId;

    #[Locked]
    public int $subjectId;

    /** @var array<string, string> what each cell shows, keyed by ScoreSheet::key() */
    public array $cells = [];

    public function mount(Stream $stream, Subject $subject): void
    {
        $term = Term::current()->firstOrFail();
        abort_unless(TaskRules::canView(auth()->user(), $stream, $subject, $term->academic_year_id), 403);

        $this->streamId = $stream->id;
        $this->subjectId = $subject->id;

        // Every cell gets a key, even when empty: an empty PHP array reaches the browser as a JSON
        // list ([]), on which "cells.t5-s10" cannot be set, so nothing would sync.
        foreach ($this->sheet->rows as $row) {
            foreach ($this->sheet->tasks as $task) {
                $key = ScoreSheet::key($task->id, $row->student->id);
                $this->cells[$key] = ($this->sheet->scores[$key] ?? null)?->display() ?? '';
            }
        }
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
    public function sheet(): ScoreSheet
    {
        return app(BuildScoreSheet::class)->handle($this->term, $this->classStream(), $this->subject());
    }

    protected function scoreSheet(): ScoreSheet
    {
        return $this->sheet;
    }

    /**
     * A cell was left: save what was typed and show it the way it was stored.
     */
    public function updatedCells(mixed $value, string $key): void
    {
        abort_unless(preg_match('/^t(\d+)-s(\d+)$/', $key, $m) === 1, 404);
        $task = $this->sheet->tasks->firstWhere('id', (int) $m[1]) ?? abort(404);
        $row = collect($this->sheet->rows)->first(fn ($r) => $r->student->id === (int) $m[2]) ?? abort(404);

        try {
            $score = app(SaveScore::class)->handle(auth()->user(), $task, $row->student, (string) $value);
        } catch (ValidationException $e) {
            $this->addError("cells.{$key}", "{$row->student->full_name}, {$task->name}: ".collect($e->errors())->flatten()->first());

            return;
        }

        $this->resetErrorBag("cells.{$key}");
        $this->cells[$key] = $score?->display() ?? '';
        unset($this->sheet, $this->problems);
    }

    public function render(): View
    {
        return view('livewire.assessment.mark-sheet')
            ->title(__('Marks: :subject, :stream', ['subject' => $this->subject()->name, 'stream' => $this->classStream()->full_name]));
    }
}
