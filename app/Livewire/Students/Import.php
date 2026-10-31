<?php

namespace App\Livewire\Students;

use App\Actions\Students\Import\PreviewStudentImport;
use App\Actions\Students\Import\StartStudentImport;
use App\Enums\ImportRowStatus;
use App\Enums\PermissionKey;
use App\Exports\StudentImportErrorReport;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use App\Models\User;
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
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
    public int $boarders = 0;

    #[Locked]
    public int $balanceCount = 0;

    #[Locked]
    public int $owedPesewas = 0;

    #[Locked]
    public int $creditPesewas = 0;

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

        $result = $preview->handle($this->path, $this->user());
        $this->problems = $result->errors;
        $this->studentCount = count($result->rows);
        $this->newGuardians = $result->newGuardians;
        $this->linkedGuardians = $result->linkedGuardians;
        $this->boarders = $result->boarders;
        $this->balanceCount = $result->balanceCount;
        $this->owedPesewas = $result->owedPesewas;
        $this->creditPesewas = $result->creditPesewas;
    }

    /**
     * The school's file back with an errors column and the problem cells highlighted. Made on
     * request from the upload being previewed; nothing is kept.
     */
    public function downloadErrors(PreviewStudentImport $preview): BinaryFileResponse
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        abort_if($this->path === null, 404);

        $name = pathinfo($this->fileName, PATHINFO_FILENAME) ?: 'students';

        return Excel::download(new StudentImportErrorReport($preview->handle($this->path, $this->user())), "{$name} - errors.xlsx");
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function startImport(StartStudentImport $start): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        abort_if($this->path === null, 404);

        try {
            $import = $start->handle($this->user(), $this->path, $this->fileName);
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
        $this->boarders = $this->balanceCount = $this->owedPesewas = $this->creditPesewas = 0;
    }
}
