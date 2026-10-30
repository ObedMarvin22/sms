<?php

namespace App\Actions\Students\Import;

use App\Enums\ImportRowStatus;
use App\Enums\StudentImportStatus;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use Illuminate\Support\Facades\DB;

/**
 * Marks an import finished once no row is pending. Called after every chunk; the last one wins.
 */
class FinishStudentImport
{
    public function handle(int $importId): void
    {
        DB::transaction(function () use ($importId) {
            $import = StudentImport::whereKey($importId)->lockForUpdate()->first();

            if ($import === null || $import->status === StudentImportStatus::Completed
                || StudentImportRow::where('student_import_id', $importId)->where('status', ImportRowStatus::Pending)->exists()) {
                return;
            }

            $import->forceFill(['status' => StudentImportStatus::Completed, 'completed_at' => now()])->save();
        });
    }
}
