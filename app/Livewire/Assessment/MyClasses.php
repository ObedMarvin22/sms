<?php

namespace App\Livewire\Assessment;

use App\Actions\Assessment\TaskRules;
use App\Enums\SheetStatus;
use App\Models\AssessmentSheet;
use App\Models\AssessmentTask;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Marks → the classes and subjects this user sets work for in the current term.
 * Subject teachers see what they teach; heads see every subject of every class.
 *
 * @property-read Term|null $term
 * @property-read Collection<int, array{stream: Stream, subject: Subject, teacher: string|null, manage: bool, comments: bool, tasks: int, status: SheetStatus|null}> $sheets
 */
#[Title('Marks')]
class MyClasses extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Computed]
    public function term(): ?Term
    {
        return Term::current()->first();
    }

    /**
     * @return Collection<int, array{stream: Stream, subject: Subject, teacher: string|null, manage: bool, comments: bool, tasks: int, status: SheetStatus|null}>
     */
    #[Computed]
    public function sheets(): Collection
    {
        if ($this->term === null) {
            return collect();
        }

        $counts = AssessmentTask::where('term_id', $this->term->id)
            ->selectRaw('stream_id, subject_id, COUNT(*) AS n')->groupBy('stream_id', 'subject_id')->get()
            ->mapWithKeys(fn ($row) => ["{$row->stream_id}-{$row->subject_id}" => (int) $row->getAttribute('n')]);

        $statuses = AssessmentSheet::where('term_id', $this->term->id)->get()
            ->mapWithKeys(fn (AssessmentSheet $sheet) => ["{$sheet->stream_id}-{$sheet->subject_id}" => $sheet->status]);

        $needle = mb_strtolower(trim($this->search));

        return TaskRules::sheetsFor(auth()->user(), $this->term->academic_year_id)
            ->filter(fn (array $s) => $needle === '' || str_contains(mb_strtolower("{$s['stream']->full_name} {$s['subject']->name} {$s['teacher']}"), $needle))
            ->map(fn (array $s) => [
                ...$s,
                'tasks' => $counts->get("{$s['stream']->id}-{$s['subject']->id}", 0),
                'status' => $statuses->get("{$s['stream']->id}-{$s['subject']->id}"),
            ])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.assessment.my-classes');
    }
}
