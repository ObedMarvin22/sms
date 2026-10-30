<?php

namespace App\Livewire\Guardians;

use App\Actions\Guardians\UpdateGuardian;
use App\Enums\PermissionKey;
use App\Models\Guardian;
use App\Support\Phone;
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

/**
 * Every guardian in the school with their children. Guardians are added from a student's page.
 */
#[Title('Guardians')]
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
     * Searches name and phone; a typed phone in any format (024…, +233…) matches.
     *
     * @return LengthAwarePaginator<int, Guardian>
     */
    #[Computed]
    public function guardians(): LengthAwarePaginator
    {
        $term = trim($this->search);
        $phone = Phone::normalise($term);
        // Part of a number: digits without the leading 0, so "0244 12" finds +233 24412…
        $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');

        return Guardian::query()
            ->with('links.student')
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->when(strlen($digits) >= 3, fn ($q) => $q->orWhere('phone', 'like', "%{$digits}%"))
                ->when($phone !== null, fn ($q) => $q->orWhere('phone', $phone))))
            ->orderBy('name')
            ->paginate(25);
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();
        $guardian = Guardian::findOrFail($id);

        $this->editingId = $guardian->id;
        $this->form = [
            'name' => $guardian->name,
            'phone' => $guardian->phone,
            'alt_phone' => (string) $guardian->alt_phone,
            'email' => (string) $guardian->email,
            'occupation' => (string) $guardian->occupation,
            'address' => (string) $guardian->address,
        ];
        Flux::modal('guardian-details')->show();
    }

    public function save(UpdateGuardian $updateGuardian): void
    {
        $this->authorize(PermissionKey::StudentsManage->value);
        $this->resetErrorBag();

        try {
            $updateGuardian->handle(Guardian::findOrFail($this->editingId), $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->guardians);
        Flux::modal('guardian-details')->close();
        Flux::toast(variant: 'success', text: __('Guardian saved.'));
    }

    public function render(): View
    {
        return view('livewire.guardians.index');
    }
}
