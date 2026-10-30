<?php

namespace App\Jobs;

/**
 * A queued job that works inside one school. Add RunInSchool to its middleware().
 */
interface SchoolJob
{
    public function schoolId(): int;
}
