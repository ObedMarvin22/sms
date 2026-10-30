<?php

namespace App\Livewire\Subjects;

use App\Actions\Subjects\CreateSubject;
use App\Actions\Subjects\DeleteSubject;
use App\Actions\Subjects\GenerateDefaultSubjects;
use App\Actions\Subjects\UpdateSubject;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Models\Subject;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Subjects')]
class Index extends Component
{
    #[Url]
    public string $level = '';

    public string $name = '';

    public string $code = '';

    /** @var list<string> */
    public array $levels = [];

    #[Locked]
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
    }

    /**
     * @return list<SchoolLevel>
     */
    public function offeredLevels(): array
    {
        return array_values(app(CurrentSchool::class)->require()->levels_offered->all());
    }

    public function offersShs(): bool
    {
        return in_array(SchoolLevel::Shs, $this->offeredLevels(), true);
    }

    /**
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function subjects(): Collection
    {
        return Subject::query()
            ->when($this->level !== '', fn ($q) => $q->whereJsonContains('levels', $this->level))
            ->orderBy('name')
            ->get();
    }

    public function generateDefaults(GenerateDefaultSubjects $generate): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $created = $generate->handle();
        unset($this->subjects);

        Flux::toast(variant: 'success', text: $created > 0
            ? trans_choice('{1} :count subject created.|[2,*] :count subjects created.', $created, ['count' => $created])
            : __('All the preset subjects already exist.'));
    }

    public function startCreate(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->name = '';
        $this->code = '';
        $this->levels = $this->level !== '' ? [$this->level] : [];

        Flux::modal('subject')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $subject = Subject::findOrFail($id);
        $this->editingId = $subject->id;
        $this->name = $subject->name;
        $this->code = (string) $subject->code;
        $this->levels = array_values($subject->levels->map(fn (SchoolLevel $l) => $l->value)->all());

        Flux::modal('subject')->show();
    }

    public function save(CreateSubject $createSubject, UpdateSubject $updateSubject): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        try {
            $this->editingId === null
                ? $createSubject->handle($this->name, $this->code, $this->levels)
                : $updateSubject->handle(Subject::findOrFail($this->editingId), $this->name, $this->code, $this->levels);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->subjects);
        Flux::modal('subject')->close();
        Flux::toast(variant: 'success', text: __('Subject saved.'));
    }

    public function delete(int $id, DeleteSubject $deleteSubject): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $deleteSubject->handle(Subject::findOrFail($id));
        unset($this->subjects);
    }

    public function render(): View
    {
        return view('livewire.subjects.index');
    }
}
