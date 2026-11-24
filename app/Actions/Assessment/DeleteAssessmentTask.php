<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentScore;
use App\Models\AssessmentTask;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteAssessmentTask
{
    public function handle(User $by, AssessmentTask $task): void
    {
        $task->loadMissing(['term', 'stream.schoolClass', 'subject']);
        TaskRules::authorize($by, $task->stream, $task->subject, $task->term->academic_year_id);
        SheetLock::ensureOpen($task->term_id, $task->stream_id, $task->subject_id);

        if (AssessmentScore::where('assessment_task_id', $task->id)->exists()) {
            throw ValidationException::withMessages(['task' => "{$task->name} has marks. Clear them on the mark sheet before deleting it."]);
        }

        $task->delete();
    }
}
