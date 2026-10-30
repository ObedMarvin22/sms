<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\InstalmentPlan;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the term is created in that school.
 *
 * @extends Factory<InstalmentPlan>
 */
class InstalmentPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id' => fn (array $a) => Term::factory()->for(AcademicYear::factory()->state(['school_id' => $a['school_id']]))->create()->id,
            'name' => 'Plan '.fake()->unique()->numberBetween(1, 99999),
            'is_default' => false,
        ];
    }
}
