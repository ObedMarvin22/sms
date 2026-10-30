<?php

namespace App\Actions\Students\Import;

use App\Jobs\ImportStudentsChunk;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Checks the file again (it may have been a while since the preview), refuses it if any row has
 * an error, stores the rows and queues them 100 at a time. The uploaded file is then deleted:
 * the rows hold everything the jobs need.
 */
class StartStudentImport
{
    public const CHUNK = 100;

    public function __construct(private PreviewStudentImport $preview, private CurrentSchool $currentSchool) {}

    /**
     * @param  string  $path  on the "local" (private) disk
     */
    public function handle(User $user, string $path, string $fileName): StudentImport
    {
        $preview = $this->preview->handle($path);

        if (! $preview->isClean()) {
            throw ValidationException::withMessages(['file' => 'The file has errors. Fix them and upload it again; nothing was imported.']);
        }

        $import = DB::transaction(function () use ($user, $fileName, $preview): StudentImport {
            $import = new StudentImport(['file_name' => mb_substr($fileName, 0, 255)]);
            $import->forceFill(['user_id' => $user->id, 'total_rows' => count($preview->rows)])->save();

            foreach (array_chunk($preview->rows, 500) as $rows) {
                foreach ($rows as $row) {
                    StudentImportRow::create([
                        'student_import_id' => $import->id,
                        'row_number' => $row['row_number'],
                        'data' => ['student' => $row['student'], 'guardians' => $row['guardians']],
                    ]);
                }
            }

            return $import;
        });

        Storage::disk('local')->delete($path);

        $schoolId = $this->currentSchool->require()->id;
        StudentImportRow::where('student_import_id', $import->id)->orderBy('row_number')->pluck('id')
            ->chunk(self::CHUNK)
            ->each(fn ($ids) => ImportStudentsChunk::dispatch($schoolId, $import->id, array_values(array_map(intval(...), $ids->all()))));

        return $import;
    }
}
