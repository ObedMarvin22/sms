<?php

namespace App\Actions\Assessment;

use App\Enums\SheetStatus;
use App\Models\AssessmentSheet;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Submits a complete mark sheet, which locks it. Complete means every part of the scheme has a
 * task and every student still in the class has a mark, A or X in every box. The subject's
 * teacher or a head may submit; submitting again after an unlock locks it again.
 */
class SubmitSheet
{
    public function __construct(private BuildScoreSheet $build) {}

    public function handle(User $by, Term $term, Stream $stream, Subject $subject): AssessmentSheet
    {
        TaskRules::authorize($by, $stream, $subject, $term->academic_year_id);

        return DB::transaction(function () use ($by, $term, $stream, $subject): AssessmentSheet {
            $sheet = AssessmentSheet::where(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id])
                ->lockForUpdate()->first();

            if ($sheet?->isLocked()) {
                throw ValidationException::withMessages(['sheet' => 'These marks are already submitted.']);
            }

            $problems = SheetLock::problems($this->build->handle($term, $stream, $subject));
            if ($problems !== []) {
                throw ValidationException::withMessages(['sheet' => $problems]);
            }

            $sheet ??= new AssessmentSheet(['term_id' => $term->id, 'stream_id' => $stream->id, 'subject_id' => $subject->id]);
            $sheet->fill(['status' => SheetStatus::Submitted, 'submitted_by' => $by->id, 'submitted_at' => now()])->save();

            return $sheet;
        });
    }
}
