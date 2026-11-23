<?php

namespace App\Actions\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\ComponentKind;
use App\Enums\SchoolLevel;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScheme;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or replaces how one level is assessed in one year.
 *
 * Weighted: 1 to 8 parts with distinct names and whole-percent weights totalling 100.
 * Descriptive: no parts (teachers write comments instead). Parts keep their id when passed
 * back, so the tasks and marks of later steps stay attached; parts left out are removed.
 */
class SaveAssessmentScheme
{
    public const MAX_PARTS = 8;

    /**
     * @param  list<array{id?: int|null, name: string, kind: ComponentKind|string, weight: int|string}>  $parts
     */
    public function handle(AcademicYear $year, SchoolLevel $level, AssessmentMode $mode, array $parts = []): AssessmentScheme
    {
        if (! app(CurrentSchool::class)->require()->levels_offered->contains($level)) {
            throw ValidationException::withMessages(['level' => 'Choose a level the school offers.']);
        }

        $clean = $mode === AssessmentMode::Weighted ? self::parts($parts) : [];

        return DB::transaction(function () use ($year, $level, $mode, $clean): AssessmentScheme {
            $scheme = AssessmentScheme::firstOrNew(['academic_year_id' => $year->id, 'level' => $level]);
            $scheme->mode = $mode;
            $scheme->save();

            $keep = array_values(array_filter(array_column($clean, 'id')));
            $scheme->components()->getQuery()->whereKeyNot($keep)->get()->each->delete();

            foreach ($clean as $sequence => $part) {
                $component = $part['id'] !== null
                    ? $scheme->components()->getQuery()->findOrFail($part['id'])
                    : new AssessmentComponent(['assessment_scheme_id' => $scheme->id]);

                $component->fill([
                    'name' => $part['name'],
                    'kind' => $part['kind'],
                    'weight' => $part['weight'],
                    'sequence' => $sequence,
                ])->save();
            }

            return $scheme->load('components');
        });
    }

    /**
     * @param  list<array{id?: int|null, name: string, kind: ComponentKind|string, weight: int|string}>  $parts
     * @return list<array{id: int|null, name: string, kind: ComponentKind, weight: int}>
     */
    public static function parts(array $parts): array
    {
        if ($parts === [] || count($parts) > self::MAX_PARTS) {
            throw ValidationException::withMessages(['parts' => 'Add between 1 and '.self::MAX_PARTS.' parts.']);
        }

        $clean = [];
        $names = [];

        foreach ($parts as $i => $part) {
            $name = preg_replace('/\s+/', ' ', trim($part['name'])) ?? '';
            $kind = $part['kind'] instanceof ComponentKind ? $part['kind'] : ComponentKind::tryFrom($part['kind']);
            $weight = filter_var($part['weight'], FILTER_VALIDATE_INT);

            if ($name === '' || mb_strlen($name) > 60) {
                throw ValidationException::withMessages(["parts.$i.name" => 'Enter a name of up to 60 characters.']);
            }
            if (in_array(mb_strtolower($name), $names, true)) {
                throw ValidationException::withMessages(["parts.$i.name" => 'Two parts have this name.']);
            }
            if ($kind === null) {
                throw ValidationException::withMessages(["parts.$i.kind" => 'Choose what kind of work this is.']);
            }
            if ($weight === false || $weight < 1 || $weight > 100) {
                throw ValidationException::withMessages(["parts.$i.weight" => 'Use a whole number from 1 to 100.']);
            }

            $names[] = mb_strtolower($name);
            $id = $part['id'] ?? null;
            $clean[] = ['id' => $id === null ? null : (int) $id, 'name' => $name, 'kind' => $kind, 'weight' => $weight];
        }

        $total = array_sum(array_column($clean, 'weight'));
        if ($total !== 100) {
            throw ValidationException::withMessages(['parts' => "The weights add up to {$total}%. They must add up to 100%."]);
        }

        return $clean;
    }
}
