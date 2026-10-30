<?php

namespace App\Actions\Subjects;

use App\Models\Programme;

/**
 * Deletes a programme and its subject links. (Later, students on the programme will block this.)
 */
class DeleteProgramme
{
    public function handle(Programme $programme): void
    {
        $programme->delete();
    }
}
