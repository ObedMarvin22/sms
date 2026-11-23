<?php

namespace App\Actions\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Models\AcademicYear;
use App\Models\AssessmentScheme;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Gives every level the school offers a scheme for the year, where it has none yet.
 * Creche, Nursery and KG: comments only. Other levels: 30% continuous assessment, 70% exam.
 */
class SetUpDefaultSchemes
{
    /** @var list<array{name: string, kind: ComponentKind, weight: int}> */
    public const WEIGHTED = [
        ['name' => 'Class exercises', 'kind' => ComponentKind::ClassWork, 'weight' => 10],
        ['name' => 'Class tests', 'kind' => ComponentKind::Test, 'weight' => 10],
        ['name' => 'Project', 'kind' => ComponentKind::Project, 'weight' => 10],
        ['name' => 'End of term exam', 'kind' => ComponentKind::Exam, 'weight' => 70],
    ];

    public function __construct(private SaveAssessmentScheme $save) {}

    /**
     * @return int How many schemes were created.
     */
    public function handle(AcademicYear $year): int
    {
        return DB::transaction(function () use ($year): int {
            $existing = AssessmentScheme::where('academic_year_id', $year->id)->pluck('level')->all();
            $created = 0;

            foreach (app(CurrentSchool::class)->require()->levels_offered as $level) {
                if (in_array($level, $existing, true)) {
                    continue;
                }

                $mode = AssessmentMode::defaultFor($level);
                $this->save->handle($year, $level, $mode, $mode === AssessmentMode::Weighted ? self::WEIGHTED : []);
                $created++;
            }

            return $created;
        });
    }
}
