<?php

namespace App\Actions\Students\Import;

use App\Actions\Guardians\AddGuardian;
use App\Actions\Guardians\FindGuardianByPhone;
use App\Actions\Guardians\LinkGuardian;
use App\Actions\Students\AdmitStudent;
use App\Enums\ImportRowStatus;
use App\Models\StudentImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Imports one checked row: admits the student, then adds or links each guardian, all in one
 * transaction. Only a pending row is imported, so running it twice changes nothing.
 *
 * If something changed since the preview (e.g. someone typed in the same admission number),
 * the row is marked failed with the reason and the rest of the import carries on.
 */
class ImportStudentRow
{
    public function __construct(
        private AdmitStudent $admitStudent,
        private FindGuardianByPhone $findGuardian,
        private AddGuardian $addGuardian,
        private LinkGuardian $linkGuardian,
    ) {}

    public function handle(int $rowId): void
    {
        try {
            DB::transaction(function () use ($rowId) {
                $row = StudentImportRow::whereKey($rowId)->lockForUpdate()->first();
                if ($row === null || $row->status !== ImportRowStatus::Pending) {
                    return;
                }

                $student = $this->admitStudent->handle($row->data['student']);

                foreach ($row->data['guardians'] as $guardian) {
                    $existing = $this->findGuardian->handle($guardian['phone']);
                    $existing === null
                        ? $this->addGuardian->handle($student, $guardian)
                        : $this->linkGuardian->handle($student, $existing, $guardian);
                }

                $row->forceFill(['status' => ImportRowStatus::Imported, 'student_id' => $student->id])->save();
            }, attempts: 5); // the whole row is retried if MySQL reports a deadlock
        } catch (ValidationException $e) {
            $message = collect($e->errors())->map(fn (array $messages, string $field) => "{$field}: {$messages[0]}")->implode(' ');

            StudentImportRow::whereKey($rowId)->where('status', ImportRowStatus::Pending)
                ->update(['status' => ImportRowStatus::Failed, 'error' => mb_substr($message, 0, 500)]);
        }
    }
}
