<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->unique()->numberBetween(2000, 2090);

        return [
            'school_id' => School::factory(),
            'name' => $start.'/'.($start + 1),
            'starts_on' => "{$start}-09-01",
            'ends_on' => ($start + 1).'-07-31',
        ];
    }
}
