<?php

namespace App\Actions\Assessment;

use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copies last year's schemes into a new year, for levels still offered that have none there.
 * Schemes already set up in the target year are left as they are.
 */
class CopySchemesFromYear
{
    public function __construct(private SaveAssessmentScheme $save) {}

    /**
     * @return int How many schemes were copied.
     */
    public function handle(AcademicYear $from, AcademicYear $to): int
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['year' => 'Choose a different year to copy from.']);
        }

        $offered = app(CurrentSchool::class)->require()->levels_offered;

        return DB::transaction(function () use ($from, $to, $offered): int {
            $existing = AssessmentScheme::where('academic_year_id', $to->id)->pluck('level')->all();
            $copied = 0;

            foreach (AssessmentScheme::with('components')->where('academic_year_id', $from->id)->get() as $scheme) {
                if (in_array($scheme->level, $existing, true) || ! $offered->contains($scheme->level)) {
                    continue;
                }

                $this->save->handle($to, $scheme->level, $scheme->mode, array_values($scheme->components->map(fn (AssessmentComponent $c) => [
                    'name' => $c->name,
                    'kind' => $c->kind,
                    'weight' => $c->weight,
                ])->all()));
                $copied++;
            }

            return $copied;
        });
    }
}
