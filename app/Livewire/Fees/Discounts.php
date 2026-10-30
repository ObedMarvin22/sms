<?php

namespace App\Livewire\Fees;

use App\Actions\Fees\SaveDiscount;
use App\Enums\DiscountKind;
use App\Enums\DiscountType;
use App\Enums\PermissionKey;
use App\Models\Discount;
use App\Models\FeeItem;
use App\Models\StudentDiscount;
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
 * The school's discounts (edited here) and every student's scholarship (given on the student's
 * page, listed here).
 */
#[Title('Discounts and scholarships')]
class Discounts extends Component
{
    #[Locked]
    public ?int $editingId = null;

    /** @var array{name: string, kind: string, type: string, value: string, whole_bill: bool, fee_item_ids: list<string>, is_active: bool} */
    public array $form = ['name' => '', 'kind' => '', 'type' => '', 'value' => '', 'whole_bill' => true, 'fee_item_ids' => [], 'is_active' => true];

    public function mount(): void
    {
        $this->authorize(PermissionKey::FinanceView->value);
    }

    public function canManage(): bool
    {
        return Auth::user()?->can(PermissionKey::FeesManage->value) ?? false;
    }

    /**
     * @return Collection<int, Discount>
     */
    #[Computed]
    public function discounts(): Collection
    {
        return Discount::orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * Students given each discount by hand: discount id => count.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function studentCounts(): array
    {
        return StudentDiscount::whereNotNull('discount_id')->selectRaw('discount_id, count(*) as students')
            ->groupBy('discount_id')->pluck('students', 'discount_id')->map(intval(...))->all();
    }

    /**
     * @return Collection<int, StudentDiscount>
     */
    #[Computed]
    public function scholarships(): Collection
    {
        return StudentDiscount::with(['student', 'endsTerm'])->whereNull('discount_id')->orderBy('name')->get();
    }

    /**
     * @return array<int, string> fee item id => name
     */
    #[Computed]
    public function itemNames(): array
    {
        return FeeItem::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return list<DiscountKind>
     */
    public function kinds(): array
    {
        return DiscountKind::cases();
    }

    /**
     * @return list<DiscountType>
     */
    public function types(): array
    {
        return DiscountType::cases();
    }

    public function startCreate(): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['name' => '', 'kind' => DiscountKind::Custom->value, 'type' => DiscountType::Percent->value, 'value' => '', 'whole_bill' => true, 'fee_item_ids' => [], 'is_active' => true];
        Flux::modal('discount')->show();
    }

    public function startEdit(int $id): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();
        $discount = Discount::findOrFail($id);
        $this->editingId = $discount->id;
        $this->form = [
            'name' => $discount->name,
            'kind' => $discount->kind->value,
            'type' => $discount->type->value,
            'value' => $discount->type->plain($discount->value),
            'whole_bill' => $discount->fee_item_ids === null,
            'fee_item_ids' => array_map(strval(...), $discount->fee_item_ids ?? []),
            'is_active' => $discount->is_active,
        ];
        Flux::modal('discount')->show();
    }

    public function save(SaveDiscount $saveDiscount): void
    {
        $this->authorize(PermissionKey::FeesManage->value);
        $this->resetErrorBag();

        try {
            $discount = $saveDiscount->handle($this->editingId === null ? null : Discount::findOrFail($this->editingId), $this->form);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->discounts);
        Flux::modal('discount')->close();
        Flux::toast(variant: 'success', text: __(':name saved.', ['name' => $discount->name]));
    }

    public function render(): View
    {
        return view('livewire.fees.discounts');
    }
}
