<?php

namespace App\Actions\Results;

use App\Enums\PermissionKey;
use App\Enums\ReportCardStatus;
use App\Enums\SheetStatus;
use App\Jobs\GenerateReportCards;
use App\Models\AssessmentSheet;
use App\Models\ClassSubject;
use App\Models\ReportCard;
use App\Models\Stream;
use App\Models\Term;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Starts making a class's report cards in the background, 15 to a job. Heads only, and only
 * when every subject the class takes has been submitted, so no card goes out half empty.
 * Results are compiled first; cards already approved or published are left as they are.
 */
class GenerateClassReportCards
{
    public function __construct(private CompileClassResults $compile) {}

    /**
     * @return int How many report cards were queued.
     */
    public function handle(User $by, Term $term, Stream $stream): int
    {
        if (! $by->can(PermissionKey::GradesApprove->value)) {
            throw new AuthorizationException('Only the head can generate report cards.');
        }

        $missing = self::missingSubjects($term, $stream);
        if ($missing > 0) {
            throw ValidationException::withMessages(['generate' => trans_choice(
                '{1} 1 subject is not submitted yet. Every subject must be submitted first.|[2,*] :count subjects are not submitted yet. Every subject must be submitted first.',
                $missing, ['count' => $missing],
            )]);
        }

        $this->compile->handle($term, $stream);

        $ids = ReportCard::where(['term_id' => $term->id, 'stream_id' => $stream->id])
            ->whereIn('status', [ReportCardStatus::Draft, ReportCardStatus::Generated])
            ->orderBy('id')->pluck('id')->all();

        // Cards being remade show as not ready until their job has run.
        ReportCard::whereKey($ids)->update(['status' => ReportCardStatus::Draft->value, 'generated_at' => null]);

        $school = app(CurrentSchool::class)->require()->id;
        foreach (array_chunk(array_map(intval(...), $ids), GenerateReportCards::BATCH) as $chunk) {
            GenerateReportCards::dispatch($school, $chunk);
        }

        activity('grades')->performedOn($stream)->causedBy($by)->event('report_cards_generated')
            ->withProperties(['term_id' => $term->id, 'cards' => count($ids)])->log('report cards queued');

        return count($ids);
    }

    /**
     * Subjects the class takes whose sheet is not submitted this term.
     */
    public static function missingSubjects(Term $term, Stream $stream): int
    {
        $taken = ClassSubject::where('school_class_id', $stream->school_class_id)->count();
        $submitted = AssessmentSheet::where(['term_id' => $term->id, 'stream_id' => $stream->id, 'status' => SheetStatus::Submitted])->count();

        return max(0, $taken - $submitted);
    }
}
