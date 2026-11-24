<?php

namespace App\Livewire\Results;

use App\Actions\Assessment\TaskRules;
use App\Enums\PermissionKey;
use App\Enums\SheetStatus;
use App\Models\AcademicYear;
use App\Models\AssessmentSheet;
use App\Models\ClassSubject;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Assessment → Results: a class's term results, students down and subjects across, with each
 * student's average, aggregate (JHS/SHS) and, when the school shows them, position. Heads see
 * every class; class teachers see their own.
 *
 * @property-read Term|null $term
 * @property-read Collection<int, Stream> $streams
 * @property-read Stream|null $selectedStream
 */
#[Title('Results')]
class ClassResults extends Component
{
    #[Url(as: 'class')]
    public ?int $streamId = null;

    public function mount(): void
    {
        abort_unless($this->streams->isNotEmpty() || auth()->user()?->can(PermissionKey::GradesApprove->value), 403);

        if ($this->streamId === null || ! $this->streams->contains('id', $this->streamId)) {
            $this->streamId = $this->streams->first()?->id;
        }
    }

    #[Computed]
    public function term(): ?Term
    {
        return Term::current()->first();
    }

    /**
     * @return Collection<int, Stream>
     */
    #[Computed]
    public function streams(): Collection
    {
        $query = Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name');

        if (auth()->user()?->can(PermissionKey::GradesApprove->value)) {
            return $query->get();
        }

        $yearId = AcademicYear::current()->value('id');

        return $yearId === null ? new Collection : $query->whereIn('streams.id', TaskRules::classTeacherStreamIds(auth()->user(), (int) $yearId))->get();
    }

    #[Computed]
    public function selectedStream(): ?Stream
    {
        return $this->streams->firstWhere('id', $this->streamId);
    }

    public function showPositions(): bool
    {
        return (bool) SchoolSettings::for(app(CurrentSchool::class)->require())->get('show_positions', false);
    }

    public function render(): View
    {
        $data = ['subjects' => collect(), 'cards' => collect(), 'results' => collect(), 'submitted' => 0];

        if ($this->term !== null && $this->selectedStream !== null) {
            $subjectIds = ClassSubject::where('school_class_id', $this->selectedStream->school_class_id)->pluck('subject_id');
            $data['subjects'] = Subject::whereKey($subjectIds)->orderBy('name')->get();
            $data['cards'] = ReportCard::with('student')->where(['term_id' => $this->term->id, 'stream_id' => $this->selectedStream->id])->get()
                ->sortBy(fn (ReportCard $c) => [$c->student->last_name, $c->student->first_name])->values();
            $data['results'] = TermResult::where(['term_id' => $this->term->id, 'stream_id' => $this->selectedStream->id])->get()
                ->keyBy(fn (TermResult $r) => "{$r->student_id}-{$r->subject_id}");
            $data['submitted'] = AssessmentSheet::where(['term_id' => $this->term->id, 'stream_id' => $this->selectedStream->id, 'status' => SheetStatus::Submitted])->count();
        }

        return view('livewire.results.class-results', $data);
    }
}
