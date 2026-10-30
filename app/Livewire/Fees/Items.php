<?php

namespace App\Livewire\Fees;

use App\Actions\Fees\CreateFeeItem;
use App\Actions\Fees\UpdateFeeItem;
use App\Enums\FeeCategory;
use App\Enums\PermissionKey;
use App\Models\FeeItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The school's fee items. Bursars (fees.manage) change them; finance.view may look.
 */
#[Title('Fee items')]
class Items extends Component
{
    #[Locked]
    public ?int $editingId = null;

    /** @var array{name: string, category: string, is_optional: bool, is_active: bool} */
    public array $form = ['name' => '', 'category' => '', 'is_optional' => false, 'is_active' => true];

    public function mount(): void
    {
        $this->authorize(PermissionKey::FinanceView->value);
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    /**
     * Active items first, then archived; by name.
     *
     * @return Collection<int, FeeItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return FeeItem::orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * @return list<FeeCategory>
     */
    public function categories(): array
    {
        return FeeCategory::cases();
    }

    public function startCreate(): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['name' => '', 'category' => FeeCategory::Tuition->value, 'is_optional' => false, 'is_active' => true];
        Flux::modal('fee-item')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $item = FeeItem::findOrFail($id);
        $this->editingId = $item->id;
        $this->form = ['name' => $item->name, 'category' => $item->category->value, 'is_optional' => $item->is_optional, 'is_active' => $item->is_active];
        Flux::modal('fee-item')->show();
    }

    public function save(CreateFeeItem $createFeeItem, UpdateFeeItem $updateFeeItem): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();

        try {
            $item = $this->editingId === null
                ? $createFeeItem->handle($this->form)
                : $updateFeeItem->handle(FeeItem::findOrFail($this->editingId), $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->items);
        Flux::modal('fee-item')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $item->name]));
    }

    public function render(): View
    {
        return view('livewire.fees.items');
    }
}
