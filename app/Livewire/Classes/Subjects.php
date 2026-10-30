<?php

namespace App\Livewire\Classes;

use App\Actions\Classes\ApplyDefaultClassSubjects;
use App\Actions\Classes\SetClassSubjects;
use App\Enums\PermissionKey;
use App\Models\SchoolClass;
use App\Models\Subject;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Class subjects')]
class Subjects extends Component
{
    #[Url(as: 'class')]
    public ?int $classId = null;

    /** @var list<string> */
    public array $selected = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);

        $this->selectClass($this->classId ?? SchoolClass::orderBy('sequence')->value('id'));
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::withCount('subjects')->orderBy('sequence')->get();
    }

    #[Computed]
    public function currentClass(): ?SchoolClass
    {
        return $this->classId === null ? null : SchoolClass::find($this->classId);
    }

    /**
     * Subjects taught at the selected class's level.
     *
     * @return Collection<int, Subject>
     */
    #[Computed]
    public function levelSubjects(): Collection
    {
        $class = $this->currentClass();

        return $class === null
            ? new Collection
            : Subject::whereJsonContains('levels', $class->level->value)->orderBy('name')->get();
    }

    public function selectClass(?int $classId): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();

        $class = $classId === null ? null : SchoolClass::with('subjects')->find($classId);
        $this->classId = $class?->id;
        $this->selected = $class === null ? [] : array_values($class->subjects->map(fn (Subject $s) => (string) $s->id)->all());
        unset($this->currentClass, $this->levelSubjects);
    }

    public function save(SetClassSubjects $setClassSubjects): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $this->resetErrorBag();
        $class = SchoolClass::findOrFail($this->classId);

        try {
            $setClassSubjects->handle($class, array_map('intval', $this->selected));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->classes);
        Flux::toast(variant: 'success', text: __(':class subjects saved.', ['class' => $class->name]));
    }

    public function applyDefaults(ApplyDefaultClassSubjects $applyDefaults): void
    {
        $this->authorize(PermissionKey::SettingsManage->value);
        $created = $applyDefaults->handle();
        unset($this->classes);
        $this->selectClass($this->classId);

        Flux::toast(variant: 'success', text: $created > 0
            ? __('Added :count subject links.', ['count' => $created])
            : __('Every class already has its level\'s subjects.'));
    }

    public function render(): View
    {
        return view('livewire.classes.subjects');
    }
}
