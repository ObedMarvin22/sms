<?php

namespace App\Actions\Classes;

use App\Models\Enrolment;
use App\Models\SchoolClass;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a class and its streams, unless students have ever been enrolled in it.
 */
class DeleteClass
{
    public function handle(SchoolClass $class): void
    {
        if (Enrolment::whereIn('stream_id', $class->streams()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['class' => "{$class->name} has student enrolments, so it cannot be deleted."]);
        }

        $class->delete();
    }
}
