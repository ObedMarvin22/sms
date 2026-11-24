<?php

namespace App\Actions\Assessment;

use App\Models\AssessmentTask;
use App\Models\User;

class DeleteAssessmentTask
{
    public function handle(User $by, AssessmentTask $task): void
    {
        $task->loadMissing(['term', 'stream.schoolClass', 'subject']);
        TaskRules::authorize($by, $task->stream, $task->subject, $task->term->academic_year_id);

        // Step 3: a task with marks will be refused here.
        $task->delete();
    }
}
