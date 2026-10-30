<?php

namespace App\Actions\Subjects;

use App\Models\Subject;

/**
 * Deletes a subject (and removes it from programmes). From Sprint 5, scores will block this.
 */
class DeleteSubject
{
    public function handle(Subject $subject): void
    {
        $subject->delete();
    }
}
