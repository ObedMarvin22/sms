<?php

namespace App\Livewire\Assessment;

use App\Actions\Assessment\BuildScoreSheet;
use App\Actions\Assessment\CommentRules;
use App\Actions\Assessment\SaveComment;
use App\Actions\Assessment\ScoreRules;
use App\Actions\Assessment\TaskRules;
use App\Livewire\Assessment\Concerns\ManagesSheetLock;
use App\Models\AssessmentSheet;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Assessment\ScoreSheet;
use App\Support\Assessment\SheetRow;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Early years (Creche, Nursery, KG): a comment on each child for one learning area this term,
 * instead of marks. Each comment saves when the teacher leaves the box.
 *
 * @property-read Term $term
 * @property-read bool $canManage
 * @property-read bool $editable
 * @property-read bool $locked
 * @property-read bool $canUnlock
 * @property-read AssessmentSheet|null $sheetRecord
 * @property-read list<string> $problems
 * @property-read list<SheetRow> $rows
 */
class CommentSheet extends Component
{
    use ManagesSheetLock;

    #[Locked]
    public int $streamId;

    #[Locked]
    public int $subjectId;

    /** @var array<string, string> each child's comment, keyed "s{student id}" (string keys: an object in the browser) */
    public array $texts = [];

    public function mount(Stream $stream, Subject $subject): void
    {
        $term = Term::current()->firstOrFail();
        abort_unless(TaskRules::canView(auth()->user(), $stream, $subject, $term->academic_year_id), 403);

        $this->streamId = $stream->id;
        $this->subjectId = $subject->id;

        if (! TaskRules::isCommentsOnly($stream, $term->academic_year_id)) {
            $this->redirectRoute('assessment.tasks', [$stream, $subject], navigate: true);

            return;
        }

        $comments = CommentRules::comments($term, $stream, $subject);
        foreach ($this->rows as $row) {
            $this->texts['s'.$row->student->id] = $comments[$row->student->id]->comment ?? '';
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

    /**
     * @return list<SheetRow>
     */
    #[Computed]
    public function rows(): array
    {
        return ScoreRules::roster($this->term, $this->classStream(), $this->subject());
    }

    /**
     * Not used for comment sheets (problems() checks comments), but the shared trait needs it.
     */
    protected function scoreSheet(): ScoreSheet
    {
        return app(BuildScoreSheet::class)->handle($this->term, $this->classStream(), $this->subject());
    }

    public function updatedTexts(mixed $value, string $key): void
    {
        abort_unless(preg_match('/^s(\d+)$/', $key, $m) === 1, 404);
        $row = collect($this->rows)->first(fn (SheetRow $r) => $r->student->id === (int) $m[1]) ?? abort(404);

        try {
            $comment = app(SaveComment::class)->handle(auth()->user(), $this->term, $this->classStream(), $this->subject(), $row->student, (string) $value);
        } catch (ValidationException $e) {
            $this->addError("texts.{$key}", "{$row->student->full_name}: ".collect($e->errors())->flatten()->first());

            return;
        }

        $this->resetErrorBag("texts.{$key}");
        $this->texts[$key] = $comment->comment ?? '';
        unset($this->problems);
    }

    public function render(): View
    {
        return view('livewire.assessment.comment-sheet')
            ->title(__('Comments: :subject, :stream', ['subject' => $this->subject()->name, 'stream' => $this->classStream()->full_name]));
    }
}
