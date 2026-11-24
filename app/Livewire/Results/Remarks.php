<?php

namespace App\Livewire\Results;

use App\Actions\Assessment\TaskRules;
use App\Actions\Results\CompileClassResults;
use App\Actions\Results\FillSuggestedHeadRemarks;
use App\Actions\Results\SaveReportRemarks;
use App\Enums\PermissionKey;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Term;
use App\Support\Results\AttendanceSummary;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Assessment → Remarks: the words on each report card. The class teacher writes a remark,
 * conduct and interest per child; the head writes (or accepts suggested) head's remarks.
 * Each box saves when the teacher leaves it.
 *
 * @property-read Term|null $term
 * @property-read Collection<int, Stream> $streams
 * @property-read Stream|null $selectedStream
 * @property-read Collection<int, ReportCard> $cards
 * @property-read bool $isHead
 */
#[Title('Remarks')]
class Remarks extends Component
{
    #[Url(as: 'class')]
    public ?int $streamId = null;

    /** @var array<string, array{teacher_remark: string, conduct: string, interest: string, head_remark: string}> keyed "s{student id}" */
    public array $remarks = [];

    public function mount(): void
    {
        abort_if($this->streams->isEmpty() && ! $this->isHead, 403);

        if ($this->streamId === null || ! $this->streams->contains('id', $this->streamId)) {
            $this->streamId = $this->streams->first()?->id;
        }
        $this->load();
    }

    #[Computed]
    public function term(): ?Term
    {
        return Term::current()->first();
    }

    #[Computed]
    public function isHead(): bool
    {
        return auth()->user()?->can(PermissionKey::GradesApprove->value) ?? false;
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

        if ($this->isHead) {
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

    /**
     * @return Collection<int, ReportCard>
     */
    #[Computed]
    public function cards(): Collection
    {
        if ($this->term === null || $this->selectedStream === null) {
            return new Collection;
        }

        return ReportCard::with('student')->where(['term_id' => $this->term->id, 'stream_id' => $this->selectedStream->id])->get()
            ->sortBy(fn (ReportCard $c) => [$c->student->last_name, $c->student->first_name])->values();
    }

    /**
     * @return array<int, array{present: int, total: int}>
     */
    public function attendance(): array
    {
        return $this->term === null ? [] : AttendanceSummary::for($this->term, array_values($this->cards->pluck('student_id')->all()));
    }

    public function updatedStreamId(): void
    {
        unset($this->selectedStream, $this->cards);
        $this->load();
    }

    public function updatedRemarks(mixed $value, string $key): void
    {
        abort_unless(preg_match('/^s(\d+)\.(teacher_remark|conduct|interest|head_remark)$/', $key, $m) === 1, 404);
        $student = Student::findOrFail((int) $m[1]);

        try {
            $card = app(SaveReportRemarks::class)->handle(auth()->user(), $this->term ?? abort(404), $this->selectedStream ?? abort(404), $student, [$m[2] => (string) $value]);
        } catch (ValidationException $e) {
            $this->addError("remarks.{$key}", "{$student->full_name}: ".collect($e->errors())->flatten()->first());

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->resetErrorBag("remarks.{$key}");
        $this->remarks['s'.$student->id][$m[2]] = (string) $card->getAttribute($m[2]);
    }

    public function fillSuggested(FillSuggestedHeadRemarks $fill): void
    {
        $filled = $fill->handle(auth()->user(), $this->term ?? abort(404), $this->selectedStream ?? abort(404));
        unset($this->cards);
        $this->load();

        Flux::toast(variant: 'success', text: trans_choice('{0} Every child already has a head\'s remark.|{1} 1 remark filled in. Read and change it as you wish.|[2,*] :count remarks filled in. Read and change them as you wish.', $filled, ['count' => $filled]));
    }

    public function render(): View
    {
        return view('livewire.results.remarks');
    }

    /**
     * Every student in the class gets a report card to write on, then the boxes are filled.
     */
    private function load(): void
    {
        $this->remarks = [];
        if ($this->term === null || $this->selectedStream === null) {
            return;
        }

        app(CompileClassResults::class)->handle($this->term, $this->selectedStream);
        unset($this->cards);

        foreach ($this->cards as $card) {
            $this->remarks['s'.$card->student_id] = [
                'teacher_remark' => (string) $card->getAttribute('teacher_remark'),
                'conduct' => (string) $card->getAttribute('conduct'),
                'interest' => (string) $card->getAttribute('interest'),
                'head_remark' => (string) $card->getAttribute('head_remark'),
            ];
        }
    }
}
