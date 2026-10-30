<?php

namespace App\Livewire\Classes;

use App\Actions\Classes\AddStream;
use App\Actions\Classes\CreateClass;
use App\Actions\Classes\DeleteClass;
use App\Actions\Classes\DeleteStream;
use App\Actions\Classes\GenerateDefaultClasses;
use App\Actions\Classes\RenameClass;
use App\Actions\Classes\RenameStream;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Support\Tenancy\CurrentSchool;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Classes')]
class Index extends Component
{
    public string $newLevel = '';

    public string $newName = '';

    /** @var array<int, string> class id => new stream name */
    public array $newStream = [];

    #[Locked]
    public ?string $renameType = null;

    #[Locked]
    public ?int $renameId = null;

    public string $renameValue = '';

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->newLevel = $this->levels()[0]->value ?? '';
    }

    /**
     * @return list<SchoolLevel>
     */
    public function levels(): array
    {
        return array_values(app(CurrentSchool::class)->require()->levels_offered->all());
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::with('streams')->orderBy('sequence')->get();
    }

    public function generateDefaults(GenerateDefaultClasses $generate): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $created = $generate->handle();
        unset($this->classes);

        Flux::toast(variant: 'success', text: $created > 0
            ? trans_choice('{1} :count class created.|[2,*] :count classes created.', $created, ['count' => $created])
            : __('All the usual classes already exist.'));
    }

    public function createClass(CreateClass $createClass): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        $this->attempt(fn () => $createClass->handle(SchoolLevel::from($this->newLevel), $this->newName), 'new');
        if ($this->getErrorBag()->isEmpty()) {
            $this->newName = '';
            Flux::toast(variant: 'success', text: __('Class added.'));
        }
    }

    public function addStream(int $classId, AddStream $addStream): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        $this->attempt(fn () => $addStream->handle(SchoolClass::findOrFail($classId), $this->newStream[$classId] ?? ''), "stream.{$classId}");
        if ($this->getErrorBag()->isEmpty()) {
            unset($this->newStream[$classId]);
        }
    }

    public function startRename(string $type, int $id): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        $this->renameType = $type === 'stream' ? 'stream' : 'class';
        $this->renameId = $id;
        $this->renameValue = $this->renameType === 'stream' ? Stream::findOrFail($id)->name : SchoolClass::findOrFail($id)->name;

        Flux::modal('rename')->show();
    }

    public function saveRename(RenameClass $renameClass, RenameStream $renameStream): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        $this->attempt(fn () => $this->renameType === 'stream'
            ? $renameStream->handle(Stream::findOrFail($this->renameId), $this->renameValue)
            : $renameClass->handle(SchoolClass::findOrFail($this->renameId), $this->renameValue), 'rename');

        if ($this->getErrorBag()->isEmpty()) {
            Flux::modal('rename')->close();
        }
    }

    public function deleteClass(int $classId, DeleteClass $deleteClass): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        try {
            $deleteClass->handle(SchoolClass::findOrFail($classId));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());
        }

        unset($this->classes);
    }

    public function deleteStream(int $streamId, DeleteStream $deleteStream): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        try {
            $deleteStream->handle(Stream::findOrFail($streamId));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->validator->errors()->first());
        }

        unset($this->classes);
    }

    public function render(): View
    {
        return view('livewire.classes.index');
    }

    /**
     * Run an Action; show its validation errors under the given form key.
     */
    private function attempt(callable $action, string $errorKey): void
    {
        try {
            $action();
            unset($this->classes);
        } catch (ValidationException $e) {
            $this->addError($errorKey, $e->validator->errors()->first());
        }
    }
}
