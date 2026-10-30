<?php

namespace App\Jobs;

use App\Actions\Billing\BillRunStudents;
use App\Jobs\Middleware\RunInSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Up to 50 students of a billing run. Safe to retry: a billed student is never billed twice.
 */
class BillStudentsChunk implements SchoolJob, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before a retry. */
    public int $backoff = 30;

    /**
     * The worker stops after 50 seconds (SPEC section 3); 50 bills take well under that.
     */
    public int $timeout = 45;

    /**
     * @param  list<int>  $studentIds
     */
    public function __construct(public int $school, public int $runId, public array $studentIds) {}

    public function schoolId(): int
    {
        return $this->school;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunInSchool];
    }

    public function handle(BillRunStudents $billRunStudents): void
    {
        $billRunStudents->handle($this->runId, $this->studentIds);
    }
}
