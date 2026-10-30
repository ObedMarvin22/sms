<?php

namespace App\Actions\Fees;

use App\Models\FeeItem;
use App\Models\StudentFeeOption;
use Illuminate\Validation\ValidationException;

/**
 * Renames, recategorises, archives or restores a fee item. Items are archived rather than
 * deleted, so structures and (later) bills keep their names.
 */
class UpdateFeeItem
{
    /**
     * @param  array{name?: string, category?: string, is_optional?: bool, is_active?: bool}  $input
     */
    public function handle(FeeItem $item, array $input): FeeItem
    {
        $optional = (bool) ($input['is_optional'] ?? $item->is_optional);

        if ($item->is_optional && ! $optional && ($takers = StudentFeeOption::where('fee_item_id', $item->id)->count()) > 0) {
            throw ValidationException::withMessages(['is_optional' => trans_choice(
                '{1} :count student takes this item. Remove it from them first.|[2,*] :count students take this item. Remove it from them first.',
                $takers, ['count' => $takers],
            )]);
        }

        $item->update([
            'name' => FeeRules::name((string) ($input['name'] ?? $item->name), $item),
            'category' => FeeRules::category((string) ($input['category'] ?? $item->category->value)),
            'is_optional' => $optional,
            'is_active' => (bool) ($input['is_active'] ?? $item->is_active),
        ]);

        return $item;
    }
}
