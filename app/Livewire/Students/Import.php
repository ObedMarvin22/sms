<?php

namespace App\Livewire\Students;

use App\Actions\Students\Import\PreviewStudentImport;
use App\Actions\Students\Import\StartStudentImport;
use App\Enums\ImportRowStatus;
use App\Enums\PermissionKey;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Template download, upload with dry run, then a queued import with a progress bar (polled).
 */
#[Title('Import students')]
class Import extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /** The uploaded file on the private disk, while its preview is shown. */
    #[Locked]
    public ?string $path = null;

    #[Locked]
    public string $fileName = '';

    /** @var list<array{row: int, field: string, message: string}> */
    #[Locked]
    public array $problems = [];

    #[Locked]
    public int $studentCount = 0;

    #[Locked]
    public int $newGuardians = 0;

    #[Locked]
    public int $linkedGuardians = 0;

    #[Locked]
    public ?int $importId = null;

    public function mount(): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->importId = StudentImport::latest('id')->value('id');
    }

    public function updatedFile(PreviewStudentImport $preview, CurrentSchool $currentSchool): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']], [
            'file.mimes' => 'Upload the .xlsx template.',
            'file.max' => 'The file must be smaller than 5 MB.',
        ]);

        $this->discardUpload();
        /** @var TemporaryUploadedFile $file */
        $file = $this->file;
        $this->fileName = $file->getClientOriginalName();
        $this->path = $file->store('imports/'.$currentSchool->require()->id, 'local') ?: null;
        $this->file = null;

        if ($this->path === null) {
            $this->addError('file', 'The file could not be saved. Try again.');

            return;
        }

        $result = $preview->handle($this->path);
        $this->problems = $result->errors;
        $this->studentCount = count($result->rows);
        $this->newGuardians = $result->newGuardians;
        $this->linkedGuardians = $result->linkedGuardians;
    }

    public function startImport(StartStudentImport $start): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        abort_if($this->path === null, 404);

        try {
            $import = $start->handle(Auth::user() ?? abort(403), $this->path, $this->fileName);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->importId = $import->id;
        $this->path = null;
        $this->problems = [];
        Flux::toast(text: __('Import started: :count students.', ['count' => $import->total_rows]));
    }

    public function cancel(): void
    {
        $this->discardUpload();
    }

    public function currentImport(): ?StudentImport
    {
        return $this->importId === null ? null : StudentImport::with('user')->find($this->importId);
    }

    /**
     * @return Collection<int, StudentImportRow>
     */
    public function failedRows(): Collection
    {
        return StudentImportRow::where('student_import_id', $this->importId)->where('status', ImportRowStatus::Failed)
            ->orderBy('row_number')->limit(200)->get();
    }

    public function render(): View
    {
        return view('livewire.students.import');
    }

    private function discardUpload(): void
    {
        if ($this->path !== null) {
            Storage::disk('local')->delete($this->path);
        }
        $this->path = null;
        $this->fileName = '';
        $this->problems = [];
        $this->studentCount = $this->newGuardians = $this->linkedGuardians = 0;
    }
}
