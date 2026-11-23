<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentComponent;
use App\Models\AssessmentTask;
use App\Models\User;

/**
 * Renames a task, moves it to another part, or changes its date or maximum mark.
 */
class UpdateAssessmentTask
{
    /**
     * @param  array{name: string, given_on: string, max_mark: int|string}  $data
     */
    public function handle(User $by, AssessmentTask $task, AssessmentComponent $component, array $data): AssessmentTask
    {
        $task->loadMissing(['term', 'stream.schoolClass', 'subject']);
        TaskRules::authorize($by, $task->stream, $task->subject, $task->term->academic_year_id);
        $clean = TaskRules::validate($task->term, $task->stream, $task->subject, $component, $data, $task);

        // Step 3: once marks exist, a lower maximum mark than the highest mark will be refused.
        $task->update(['assessment_component_id' => $component->id, ...$clean]);

        return $task;
    }
}
