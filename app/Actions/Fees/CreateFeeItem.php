<?php

namespace App\Actions\Fees;

use App\Models\FeeItem;

class CreateFeeItem
{
    /**
     * @param  array{name?: string, category?: string, is_optional?: bool}  $input
     */
    public function handle(array $input): FeeItem
    {
        return FeeItem::create([
            'name' => FeeRules::name((string) ($input['name'] ?? '')),
            'category' => FeeRules::category((string) ($input['category'] ?? '')),
            'is_optional' => (bool) ($input['is_optional'] ?? false),
        ]);
    }
}
