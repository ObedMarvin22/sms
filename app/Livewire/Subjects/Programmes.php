<?php

namespace App\Livewire\Subjects;

use App\Actions\Subjects\CreateProgramme;
use App\Actions\Subjects\DeleteProgramme;
use App\Actions\Subjects\GenerateDefaultProgrammes;
use App\Actions\Subjects\RenameProgramme;
use App\Actions\Subjects\SetProgrammeSubjects;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Enums\SubjectType;
use App\Models\Programme;
use App\Models\Subject;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('SHS programmes')]
class Programmes extends Component
{
    public string $newName = '';

    #[Locked]
    public ?int $editingId = null;

    public string $editName = '';

    /** @var list<string> */
    public array $core = [];

    /** @var list<string> */
    public array $electives = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        abort_unless(app(CurrentSchool::class)->require()->levels_offered->contains(SchoolLevel::Shs), 404);
    }

    /**
     * @return Collection<int, Programme>
     */
    #[Computed]
    public function programmes(): Collection
    {
        return Programme::with('subjects')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function shsSubjects(): Collection
    {
        return Subject::whereJsonContains('levels', SchoolLevel::Shs->value)->orderBy('name')->get();
    }

    public function generateDefaults(GenerateDefaultProgrammes $generate): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $created = $generate->handle();
        unset($this->programmes, $this->shsSubjects);

        Flux::toast(variant: 'success', text: $created > 0
            ? trans_choice('{1} :count programme created.|[2,*] :count programmes created.', $created, ['count' => $created])
            : __('All the preset programmes already exist.'));
    }

    public function create(CreateProgramme $createProgramme): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $createProgramme->handle($this->newName);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->newName = '';
        unset($this->programmes);
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $programme = Programme::with('subjects')->findOrFail($id);

        $this->editingId = $programme->id;
        $this->editName = $programme->name;
        $this->core = $this->idsOfType($programme, SubjectType::Core);
        $this->electives = $this->idsOfType($programme, SubjectType::Elective);

        Flux::modal('programme')->show();
    }

    public function save(RenameProgramme $renameProgramme, SetProgrammeSubjects $setProgrammeSubjects): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $programme = Programme::findOrFail($this->editingId);

        try {
            $renameProgramme->handle($programme, $this->editName);
            $setProgrammeSubjects->handle(
                $programme,
                array_map('intval', $this->core),
                array_map('intval', $this->electives),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->programmes);
        Flux::modal('programme')->close();
        Flux::toast(variant: 'success', text: __('Programme saved.'));
    }

    public function delete(int $id, DeleteProgramme $deleteProgramme): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $deleteProgramme->handle(Programme::findOrFail($id));
        unset($this->programmes);
    }

    public function render(): View
    {
        return view('livewire.subjects.programmes');
    }

    /**
     * @return list<string>
     */
    private function idsOfType(Programme $programme, SubjectType $type): array
    {
        return array_values($programme->subjects
            ->filter(fn (Subject $s) => $s->getRelationValue('pivot')?->getAttribute('type') === $type)
            ->map(fn (Subject $s) => (string) $s->id)
            ->all());
    }
}
