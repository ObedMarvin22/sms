<?php

namespace Database\Factories;

use App\Models\StudentImport;
use App\Models\StudentImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id: the import is created in that school.
 *
 * @extends Factory<StudentImportRow>
 */
class StudentImportRowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_import_id' => fn (array $a) => StudentImport::factory()->create(['school_id' => $a['school_id']])->id,
            'row_number' => fake()->unique()->numberBetween(2, 100_000),
            'data' => ['student' => [], 'guardians' => []],
        ];
    }
}
