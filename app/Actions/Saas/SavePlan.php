<?php

namespace App\Actions\Saas;

use App\Actions\Fees\FeeRules;
use App\Models\Plan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Creates or changes a price plan. A new rate only affects invoices issued from now on (each
 * invoice keeps the rate it was issued with). Logged.
 */
class SavePlan
{
    /**
     * @param  array{name?: mixed, rate?: mixed, description?: mixed, is_active?: mixed}  $input
     */
    public function handle(?Plan $plan, array $input): Plan
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:60', Rule::unique('plans', 'name')->ignore($plan?->id)],
            'rate' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ])->validate();

        $plan ??= new Plan;
        $plan->fill([
            'name' => trim($data['name']),
            'rate_per_student' => FeeRules::amount($data['rate'], 'rate'),
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return $plan;
    }
}
