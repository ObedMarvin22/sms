<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\AssessmentTask;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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
        SheetLock::ensureOpen($task->term_id, $task->stream_id, $task->subject_id);
        $clean = TaskRules::validate($task->term, $task->stream, $task->subject, $component, $data, $task);

        $highest = AssessmentScore::where('assessment_task_id', $task->id)->max('mark');
        if ($highest !== null && (float) $highest > $clean['max_mark']) {
            $shown = rtrim(rtrim((string) $highest, '0'), '.');
            throw ValidationException::withMessages(['max_mark' => "A student already has {$shown} on this task, so it cannot be out of less than that."]);
        }

        $task->update(['assessment_component_id' => $component->id, ...$clean]);

        return $task;
    }
}
