<?php

namespace App\Livewire\Results;

use App\Actions\Results\ApproveReportCards;
use App\Actions\Results\GenerateClassReportCards;
use App\Actions\Results\PublishReportCards;
use App\Actions\Results\WithdrawReportCards;
use App\Enums\PermissionKey;
use App\Enums\ReportCardStatus;
use App\Enums\SheetStatus;
use App\Models\AssessmentSheet;
use App\Models\ClassSubject;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Term;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Assessment → Report cards: each class's report cards this term, for heads. Generate them once
 * every subject is submitted; the page refreshes while the background jobs run.
 *
 * @property-read Term|null $term
 * @property-read list<array{stream: Stream, subjects: int, submitted: int, cards: int, statuses: array<string, int>}> $classes
 */
#[Title('Report cards')]
class ReportCards extends Component
{
    public function mount(): void
    {
        $this->authorize(PermissionKey::GradesApprove->value);
    }

    #[Computed]
    public function term(): ?Term
    {
        return Term::current()->first();
    }

    /**
     * @return list<array{stream: Stream, subjects: int, submitted: int, cards: int, statuses: array<string, int>}>
     */
    #[Computed]
    public function classes(): array
    {
        if ($this->term === null) {
            return [];
        }

        /** @var Collection<int, Stream> $streams */
        $streams = Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')->get();
        $subjects = [];
        foreach (ClassSubject::query()->selectRaw('school_class_id, COUNT(*) AS n')->groupBy('school_class_id')->toBase()->get() as $row) {
            $subjects[(int) $row->school_class_id] = (int) $row->n;
        }
        $submitted = [];
        foreach (AssessmentSheet::where(['term_id' => $this->term->id, 'status' => SheetStatus::Submitted])
            ->selectRaw('stream_id, COUNT(*) AS n')->groupBy('stream_id')->toBase()->get() as $row) {
            $submitted[(int) $row->stream_id] = (int) $row->n;
        }
        $cards = ReportCard::where('term_id', $this->term->id)->selectRaw('stream_id, status, COUNT(*) AS n')
            ->groupBy('stream_id', 'status')->toBase()->get()->groupBy('stream_id');

        $rows = [];
        foreach ($streams as $stream) {
            $statuses = array_fill_keys(array_map(fn (ReportCardStatus $s) => $s->value, ReportCardStatus::cases()), 0);
            foreach ($cards->get($stream->id, collect()) as $row) {
                $statuses[$row->status] = (int) $row->n;
            }

            $rows[] = [
                'stream' => $stream,
                'subjects' => $subjects[$stream->school_class_id] ?? 0,
                'submitted' => $submitted[$stream->id] ?? 0,
                'cards' => array_sum($statuses),
                'statuses' => $statuses,
            ];
        }

        return $rows;
    }

    public function generate(int $streamId, GenerateClassReportCards $generate): void
    {
        $this->authorize(PermissionKey::GradesApprove->value);
        $stream = Stream::findOrFail($streamId);

        try {
            $count = $generate->handle(auth()->user(), $this->term ?? abort(404), $stream);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $stream->full_name.': '.collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->classes);
        Flux::toast(variant: 'success', text: trans_choice('{0} No report cards to make for :class.|{1} Making 1 report card for :class.|[2,*] Making :count report cards for :class. This page updates as they are done.', $count, ['count' => $count, 'class' => $stream->full_name]));
    }

    public function approve(int $streamId, ApproveReportCards $approve): void
    {
        $this->step($streamId, fn (Stream $stream) => $approve->handle(auth()->user(), $this->term ?? abort(404), $stream),
            '{1} 1 report card approved for :class.|[2,*] :count report cards approved for :class.');
    }

    public function publish(int $streamId, PublishReportCards $publish): void
    {
        $this->step($streamId, fn (Stream $stream) => $publish->handle(auth()->user(), $this->term ?? abort(404), $stream),
            '{1} 1 report card published for :class. Parents can see it now.|[2,*] :count report cards published for :class. Parents can see them now.');
    }

    public function withdraw(int $streamId, WithdrawReportCards $withdraw): void
    {
        $this->step($streamId, fn (Stream $stream) => $withdraw->handle(auth()->user(), $this->term ?? abort(404), $stream),
            '{0} Nothing to withdraw for :class.|{1} 1 report card withdrawn for :class.|[2,*] :count report cards withdrawn for :class. Parents no longer see them.');
    }

    /**
     * Runs one of the head's steps for a class and reports how it went.
     *
     * @param  \Closure(Stream): int  $run
     */
    private function step(int $streamId, \Closure $run, string $message): void
    {
        $this->authorize(PermissionKey::GradesApprove->value);
        $stream = Stream::findOrFail($streamId);

        try {
            $count = $run($stream);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: (string) collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->classes);
        Flux::toast(variant: 'success', text: trans_choice($message, $count, ['count' => $count, 'class' => $stream->full_name]));
    }

    public function render(): View
    {
        return view('livewire.results.report-cards');
    }
}
