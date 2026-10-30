<?php

namespace App\Jobs;

use App\Actions\Students\Import\FinishStudentImport;
use App\Actions\Students\Import\ImportStudentRow;
use App\Jobs\Middleware\RunInSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Up to 100 rows of a student import. Safe to retry: rows already imported are skipped.
 */
class ImportStudentsChunk implements SchoolJob, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds before a retry. */
    public int $backoff = 30;

    /**
     * The worker stops after 50 seconds (SPEC section 3); 100 rows take well under that.
     */
    public int $timeout = 45;

    /**
     * @param  list<int>  $rowIds
     */
    public function __construct(public int $school, public int $importId, public array $rowIds) {}

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

    public function handle(ImportStudentRow $importRow, FinishStudentImport $finish): void
    {
        foreach ($this->rowIds as $rowId) {
            $importRow->handle($rowId);
        }

        $finish->handle($this->importId);
    }
}
