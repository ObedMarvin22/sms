<?php

namespace App\Actions\Saas;

use App\Models\Plan;
use App\Models\School;
use Illuminate\Validation\ValidationException;

/**
 * Puts a school on a price plan (logged on the school). Only active plans can be chosen.
 */
class SetSchoolPlan
{
    public function handle(School $school, ?int $planId): School
    {
        $plan = $planId === null ? null : Plan::where('is_active', true)->find($planId);
        if ($planId !== null && $plan === null) {
            throw ValidationException::withMessages(['planId' => 'Choose one of the plans in use.']);
        }

        $school->forceFill(['plan_id' => $plan?->id])->save();

        return $school;
    }
}
