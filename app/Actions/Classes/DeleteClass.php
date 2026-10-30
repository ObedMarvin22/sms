<?php

namespace App\Actions\Classes;

use App\Models\SchoolClass;

/**
 * Deletes a class and its streams. (From Sprint 3, enrolled students will block this.)
 */
class DeleteClass
{
    public function handle(SchoolClass $class): void
    {
        $class->delete();
    }
}
