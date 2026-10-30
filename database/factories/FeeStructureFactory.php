<?php

namespace Database\Factories;

use App\Enums\Residence;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the term and class are created in that school.
 *
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id' => fn (array $a) => Term::factory()->for(AcademicYear::factory()->state(['school_id' => $a['school_id']]))->create()->id,
            'school_class_id' => fn (array $a) => SchoolClass::factory()->create(['school_id' => $a['school_id']])->id,
            'residence' => Residence::Day,
        ];
    }
}
