<?php

namespace App\Actions\Fees;

use App\Models\InstalmentPlan;
use App\Models\StudentInstalmentPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an instalment plan no student follows (its steps go with it, each logged).
 */
class DeleteInstalmentPlan
{
    public function handle(InstalmentPlan $plan): void
    {
        if (($followers = StudentInstalmentPlan::where('instalment_plan_id', $plan->id)->count()) > 0) {
            throw ValidationException::withMessages(['plan' => trans_choice(
                '{1} :count student follows this plan. Move them to another plan first.|[2,*] :count students follow this plan. Move them to another plan first.',
                $followers, ['count' => $followers],
            )]);
        }

        DB::transaction(function () use ($plan): void {
            $plan->steps->each->delete();
            $plan->delete();
        });
    }
}
