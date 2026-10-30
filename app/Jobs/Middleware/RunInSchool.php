<?php

namespace App\Jobs\Middleware;

use App\Jobs\SchoolJob;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Closure;

/**
 * Job middleware: runs a queued job as the school it was dispatched for, so BelongsToSchool
 * scopes every query and guards every write inside the job, exactly as in a request.
 *
 * A queue worker has no subdomain, and CurrentSchool starts empty for each job.
 */
class RunInSchool
{
    public function handle(SchoolJob $job, Closure $next): mixed
    {
        $school = School::findOrFail($job->schoolId());

        return app(CurrentSchool::class)->runFor($school, fn () => $next($job));
    }
}
