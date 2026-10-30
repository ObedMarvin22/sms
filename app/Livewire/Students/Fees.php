<?php

namespace App\Livewire\Students;

use App\Actions\Fees\FeeRules;
use App\Actions\Fees\SetFeeOption;
use App\Actions\Fees\SetResidence;
use App\Enums\PermissionKey;
use App\Enums\Residence;
use App\Models\FeeItem;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The fees section of a student's page: day or boarding, and the optional items they take.
 * Shown to finance.view; changed by fees.manage.
 */
class Fees extends Component
{
    #[Locked]
    public int $studentId;

    public string $residence = '';

    public function mount(int $studentId): void
    {
        $this->studentId = $this->student()->id;
        $this->residence = $this->student()->residence->value;
    }

    public function canView(): bool
    {
        return Auth::user()?->can(PermissionKey::FinanceView->value) ?? false;
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    public function offersBoarding(): bool
    {
        return FeeRules::offersBoarding();
    }

    public function student(): Student
    {
        return Student::findOrFail($this->studentId);
    }

    /**
     * Optional items students can take, plus any archived one this student still has.
     *
     * @return Collection<int, FeeItem>
     */
    public function optionalItems(): Collection
    {
        return FeeItem::where('is_optional', true)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $this->takenIds()))
            ->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    public function takenIds(): array
    {
        return array_values(array_map(intval(...), $this->student()->feeOptions()->pluck('fee_item_id')->all()));
    }

    public function updatedResidence(SetResidence $setResidence): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $student = $this->student();

        try {
            $setResidence->handle($student, Residence::from($this->residence));
        } catch (ValidationException $e) {
            $this->residence = $student->residence->value;
            $this->setErrorBag($e->validator->errors());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name is now a :residence student.', [
            'name' => $student->first_name, 'residence' => mb_strtolower($student->residence->label()),
        ]));
    }

    public function toggleOption(int $itemId, SetFeeOption $setFeeOption): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();

        try {
            $setFeeOption->handle($this->student(), FeeItem::findOrFail($itemId), ! in_array($itemId, $this->takenIds(), true));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        }
    }

    public function render(): View
    {
        return view('livewire.students.fees');
    }
}
