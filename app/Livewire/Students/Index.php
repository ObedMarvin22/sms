<?php

namespace App\Livewire\Students;

use App\Actions\Students\AdmitStudent;
use App\Actions\Students\SearchStudents;
use App\Actions\Students\UpdateStudent;
use App\Enums\Gender;
use App\Enums\PermissionKey;
use App\Enums\SchoolLevel;
use App\Enums\StudentStatus;
use App\Models\Programme;
use App\Models\Stream;
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

    #[Url(as: 'stream')]
    public string $streamFilter = '';

    /** Active by default; '' shows every status. */
    #[Url(as: 'status', except: 'active')]
    public string $statusFilter = 'active';

    #[Url(as: 'gender')]
    public string $genderFilter = '';

    #[Locked]
    public ?int $editingId = null;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorize(PermissionKey::StudentsView->value);
        $this->resetForm();
    }

    /**
     * Any search or filter change goes back to page 1.
     */
    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'streamFilter', 'statusFilter', 'genderFilter'], true)) {
            $this->resetPage();
        }
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
        return app(SearchStudents::class)->handle([
            'search' => $this->search,
            'stream_id' => $this->streamFilter,
            'status' => $this->statusFilter,
            'gender' => $this->genderFilter,
        ])->with('currentEnrolment.stream.schoolClass')->paginate(25);
    }

    /**
     * @return list<StudentStatus>
     */
    public function statuses(): array
    {
        return StudentStatus::cases();
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
     * Streams to admit into, labelled like "JHS 2A", in class order.
     *
     * @return array<int, string> stream id => label
     */
    #[Computed]
    public function streamOptions(): array
    {
        return Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get(['streams.id', 'streams.name', 'school_classes.name as class_name'])
            ->mapWithKeys(fn (Stream $s) => [$s->id => $s->getAttribute('class_name').$s->name])
            ->all();
    }

    public function selectedStreamIsShs(): bool
    {
        $stream = Stream::with('schoolClass')->find((int) ($this->form['stream_id'] ?? 0));

        return $stream?->schoolClass->level === SchoolLevel::Shs;
    }

    /**
     * @return array<int, string>
     */
    public function programmeOptions(): array
    {
        return Programme::orderBy('name')->pluck('name', 'id')->all();
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
        $this->form['stream_id'] = '';
        $this->form['programme_id'] = '';
    }
}
