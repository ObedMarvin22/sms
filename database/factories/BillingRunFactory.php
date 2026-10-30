<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\BillingRun;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the term is created in that school.
 *
 * @extends Factory<BillingRun>
 */
class BillingRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id' => fn (array $a) => Term::factory()->for(AcademicYear::factory()->state(['school_id' => $a['school_id']]))->create()->id,
            'total_students' => 0,
        ];
    }
}
