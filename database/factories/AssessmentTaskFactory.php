<?php

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Models\AssessmentTask;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass school_id. Builds a term, a Primary stream, a subject and a scheme part in that school.
 *
 * @extends Factory<AssessmentTask>
 */
class AssessmentTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_id' => fn (array $a) => Term::factory()->create([
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $a['school_id']])->id,
            ])->id,
            'stream_id' => fn (array $a) => Stream::factory()->create([
                'school_id' => $a['school_id'],
                'school_class_id' => SchoolClass::factory()->create(['school_id' => $a['school_id'], 'level' => SchoolLevel::Primary])->id,
            ])->id,
            'subject_id' => fn (array $a) => Subject::factory()->create(['school_id' => $a['school_id']])->id,
            'assessment_component_id' => fn (array $a) => AssessmentComponent::factory()->create([
                'assessment_scheme_id' => AssessmentScheme::factory()->create([
                    'school_id' => $a['school_id'],
                    'academic_year_id' => Term::withoutGlobalScopes()->findOrFail((int) $a['term_id'])->academic_year_id,
                ])->id,
            ])->id,
            'name' => 'Class test '.fake()->unique()->numberBetween(1, 99999),
            'given_on' => fn (array $a) => Term::withoutGlobalScopes()->findOrFail((int) $a['term_id'])->starts_on,
            'max_mark' => 20,
        ];
    }
}
