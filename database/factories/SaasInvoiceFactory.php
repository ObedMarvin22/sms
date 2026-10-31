<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SaasInvoice;
use App\Models\School;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaasInvoice>
 */
class SaasInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'OT/INV/2026/'.fake()->unique()->numerify('####'),
            'school_id' => School::factory(),
            'term_id' => fn (array $a) => Term::factory()->create([
                'school_id' => $a['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
            'plan_name' => 'Standard',
            'rate_per_student' => 1500,
            'census_on' => '2026-09-22',
            'student_count' => 100,
            'amount' => 150000,
            'issued_at' => now(),
            'due_on' => now()->addDays(30)->toDateString(),
        ];
    }
}
