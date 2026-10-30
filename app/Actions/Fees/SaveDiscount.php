<?php

namespace App\Actions\Fees;

use App\Enums\DiscountKind;
use App\Models\Discount;
use App\Models\StudentDiscount;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes a school discount. Only one sibling discount can be in use, and a
 * discount given to students cannot become the automatic sibling kind.
 */
class SaveDiscount
{
    /**
     * @param  array{name?: string, kind?: string, type?: string, value?: string, whole_bill?: bool, fee_item_ids?: array<int|string>, is_active?: bool}  $input
     */
    public function handle(?Discount $discount, array $input): Discount
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Enter a name of up to 100 characters.']);
        }
        if (Discount::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->when($discount, fn ($q) => $q->whereKeyNot($discount->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'There is already a discount with this name.']);
        }

        $kind = DiscountKind::tryFrom((string) ($input['kind'] ?? '')) ?? throw ValidationException::withMessages(['kind' => 'Choose who the discount is for.']);
        [$type, $value] = FeeRules::reduction((string) ($input['type'] ?? ''), (string) ($input['value'] ?? ''));
        $feeItemIds = FeeRules::feeItemIds((bool) ($input['whole_bill'] ?? true), $input['fee_item_ids'] ?? []);
        $active = (bool) ($input['is_active'] ?? true);

        if ($kind === DiscountKind::Sibling && $active
            && Discount::where('kind', DiscountKind::Sibling->value)->where('is_active', true)->when($discount, fn ($q) => $q->whereKeyNot($discount->id))->exists()) {
            throw ValidationException::withMessages(['kind' => 'There is already a sibling discount in use. Change that one instead.']);
        }
        if ($discount !== null && $kind === DiscountKind::Sibling && $discount->kind !== DiscountKind::Sibling
            && StudentDiscount::where('discount_id', $discount->id)->exists()) {
            throw ValidationException::withMessages(['kind' => 'Students have been given this discount by hand, so it cannot become the automatic sibling discount.']);
        }

        $discount ??= new Discount;
        $discount->fill([
            'name' => $name,
            'kind' => $kind,
            'type' => $type,
            'value' => $value,
            'fee_item_ids' => $feeItemIds,
            'is_active' => $active,
        ])->save();

        return $discount;
    }
}
