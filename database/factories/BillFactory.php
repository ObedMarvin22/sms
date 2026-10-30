<?php

namespace Database\Factories;

use App\Enums\Residence;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\Enrolment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: every related row is created in that school.
 *
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Built while another school may be current (isolation tests), so read without scope.
            'enrolment_id' => fn (array $a) => Enrolment::factory()->create(['school_id' => $a['school_id']])->id,
            'student_id' => fn (array $a) => Enrolment::withoutGlobalScopes()->whereKey($a['enrolment_id'])->value('student_id'),
            'term_id' => fn (array $a) => Term::factory()->for(AcademicYear::factory()->state(['school_id' => $a['school_id']]))->create()->id,
            'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            'residence' => Residence::Day,
            'gross' => Money::pesewas(100_000),
            'reductions' => Money::zero(),
            'net' => Money::pesewas(100_000),
            'arrears' => Money::zero(),
            'issued_at' => now(),
        ];
    }
}
