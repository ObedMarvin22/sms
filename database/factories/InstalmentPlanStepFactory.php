<?php

namespace Database\Factories;

use App\Models\InstalmentPlan;
use App\Models\InstalmentPlanStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the plan is created in that school.
 *
 * @extends Factory<InstalmentPlanStep>
 */
class InstalmentPlanStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instalment_plan_id' => fn (array $a) => InstalmentPlan::factory()->create(['school_id' => $a['school_id']])->id,
            'sequence' => 1,
            'due_on' => '2026-09-30',
            'percent' => 10_000,
        ];
    }
}
