<?php

namespace Database\Factories;

use App\Enums\SubjectType;
use App\Models\Programme;
use App\Models\ProgrammeSubject;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeSubject>
 */
class ProgrammeSubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            // Test data may be built while another school is current, so look up without the scope.
            'school_id' => fn (array $attributes) => Programme::withoutGlobalScopes()->findOrFail((int) $attributes['programme_id'])->school_id,
            'subject_id' => fn (array $attributes) => Subject::factory()->create(['school_id' => $attributes['school_id']])->id,
            'type' => SubjectType::Elective,
        ];
    }
}
