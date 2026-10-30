<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\StudentImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentImport>
 */
class StudentImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'file_name' => 'students.xlsx',
            'total_rows' => 0,
        ];
    }
}
