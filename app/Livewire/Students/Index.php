<?php

namespace App\Livewire\Students;

use App\Actions\Students\AdmitStudent;
use App\Actions\Students\UpdateStudent;
use App\Enums\Gender;
use App\Enums\PermissionKey;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Students')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::StudentsView->value);
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::StudentsManage->value) ?? false;
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Student::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('admission_number', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25);
    }

    public function startCreate(): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();
        $this->resetForm();
        Flux::modal('student')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();
        $student = Student::findOrFail($id);

        $this->editingId = $student->id;
        $this->form = [
            'admission_number' => $student->admission_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'other_names' => (string) $student->other_names,
            'gender' => (string) $student->gender?->value,
            'date_of_birth' => (string) $student->date_of_birth?->toDateString(),
            'admitted_on' => $student->admitted_on->toDateString(),
        ];
        Flux::modal('student')->show();
    }

    public function save(AdmitStudent $admitStudent, UpdateStudent $updateStudent): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();

        try {
            $student = $this->editingId === null
                ? $admitStudent->handle($this->form)
                : $updateStudent->handle(Student::findOrFail($this->editingId), $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->students);
        Flux::modal('student')->close();
        Flux::toast(variant: 'success', text: __(':name saved (:number).', ['name' => $student->full_name, 'number' => $student->admission_number]));
    }

    /**
     * @return list<Gender>
     */
    public function genders(): array
    {
        return Gender::cases();
    }

    public function render(): View
    {
        return view('livewire.students.index');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = array_fill_keys(['admission_number', 'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth'], '');
        $this->form['admitted_on'] = now()->toDateString();
    }
}
