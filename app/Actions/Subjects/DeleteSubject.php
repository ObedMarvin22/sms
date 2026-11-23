<?php

namespace App\Actions\Subjects;

use App\Models\AssessmentTask;
use App\Models\Subject;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a subject (and removes it from programmes). A subject with assessment tasks is kept.
 */
class DeleteSubject
{
    public function handle(Subject $subject): void
    {
        if (AssessmentTask::where('subject_id', $subject->id)->exists()) {
            throw ValidationException::withMessages(['subject' => "{$subject->name} has assessment tasks, so it cannot be deleted."]);
        }

        $subject->delete();
    }
}
