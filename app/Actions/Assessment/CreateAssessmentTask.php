<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentComponent;
use App\Models\AssessmentTask;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;

/**
 * Adds a task (e.g. "Class test 1, out of 20") under a part of the scheme, for one class and
 * subject in a term. Only the subject's teacher in that class, or a head, may do this.
 */
class CreateAssessmentTask
{
    /**
     * @param  array{name: string, given_on: string, max_mark: int|string}  $data
     */
    public function handle(User $by, Term $term, Stream $stream, Subject $subject, AssessmentComponent $component, array $data): AssessmentTask
    {
        TaskRules::authorize($by, $stream, $subject, $term->academic_year_id);
        $clean = TaskRules::validate($term, $stream, $subject, $component, $data);

        return AssessmentTask::create([
            'term_id' => $term->id,
            'stream_id' => $stream->id,
            'subject_id' => $subject->id,
            'assessment_component_id' => $component->id,
            ...$clean,
            'created_by' => $by->id,
        ]);
    }
}
