<?php

namespace App\Actions\Grading;

use App\Enums\SchoolLevel;
use App\Models\GradeBand;
use App\Models\GradeScale;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or replaces a level's grade scale. Bands must cover every total from 0 to 100: one
 * band starts at 0, starts are distinct whole numbers, and grades are distinct. JHS and SHS
 * bands need points (1 is best) because their aggregates add points up.
 *
 * Results already worked out keep the grade they were given (term results are a snapshot).
 */
class SaveGradeScale
{
    public const MAX_BANDS = 12;

    /**
     * @param  list<array{min_score: int|string, grade: string, label: string, points?: int|string|null}>  $bands
     */
    public function handle(SchoolLevel $level, string $name, array $bands): GradeScale
    {
        if (! app(CurrentSchool::class)->require()->levels_offered->contains($level)) {
            throw ValidationException::withMessages(['level' => 'Choose a level the school offers.']);
        }

        $name = preg_replace('/\s+/', ' ', trim($name)) ?? '';
        if ($name === '' || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'Give the scale a name of up to 60 characters.']);
        }

        $clean = self::bands($bands, in_array($level, [SchoolLevel::Jhs, SchoolLevel::Shs], true));

        return DB::transaction(function () use ($level, $name, $clean): GradeScale {
            $scale = GradeScale::firstOrNew(['level' => $level]);
            $scale->name = $name;
            $scale->save();

            // Bands are small and have no history of their own: replace them.
            $scale->bands()->getQuery()->get()->each->delete();
            foreach ($clean as $band) {
                GradeBand::create(['grade_scale_id' => $scale->id, ...$band]);
            }

            return $scale->load('bands');
        });
    }

    /**
     * @param  list<array{min_score: int|string, grade: string, label: string, points?: int|string|null}>  $bands
     * @return list<array{min_score: int, grade: string, label: string, points: int|null}>
     */
    public static function bands(array $bands, bool $needPoints): array
    {
        if (count($bands) < 2 || count($bands) > self::MAX_BANDS) {
            throw ValidationException::withMessages(['bands' => 'Use between 2 and '.self::MAX_BANDS.' bands.']);
        }

        $clean = [];
        foreach ($bands as $i => $band) {
            $min = filter_var($band['min_score'], FILTER_VALIDATE_INT);
            $grade = strtoupper(trim($band['grade']));
            $label = preg_replace('/\s+/', ' ', trim($band['label'])) ?? '';
            $rawPoints = $band['points'] ?? null;
            $points = $rawPoints === null || $rawPoints === '' ? null : filter_var($rawPoints, FILTER_VALIDATE_INT);

            if ($min === false || $min < 0 || $min > 100) {
                throw ValidationException::withMessages(["bands.$i.min_score" => 'Use a whole number from 0 to 100.']);
            }
            if ($grade === '' || mb_strlen($grade) > 4) {
                throw ValidationException::withMessages(["bands.$i.grade" => 'Enter a grade of up to 4 characters, e.g. A1.']);
            }
            if ($label === '' || mb_strlen($label) > 40) {
                throw ValidationException::withMessages(["bands.$i.label" => 'Enter a description of up to 40 characters.']);
            }
            if ($points === false || ($points !== null && ($points < 1 || $points > 99))) {
                throw ValidationException::withMessages(["bands.$i.points" => 'Use a whole number from 1 to 99.']);
            }
            if ($needPoints && $points === null) {
                throw ValidationException::withMessages(["bands.$i.points" => 'JHS and SHS grades need points for the aggregate.']);
            }

            $clean[] = ['min_score' => $min, 'grade' => $grade, 'label' => $label, 'points' => $points];
        }

        $mins = array_column($clean, 'min_score');
        if (count(array_unique($mins)) !== count($mins)) {
            throw ValidationException::withMessages(['bands' => 'Two bands start at the same score.']);
        }
        if (! in_array(0, $mins, true)) {
            throw ValidationException::withMessages(['bands' => 'One band must start at 0, so every total gets a grade.']);
        }
        $grades = array_column($clean, 'grade');
        if (count(array_unique($grades)) !== count($grades)) {
            throw ValidationException::withMessages(['bands' => 'Two bands have the same grade.']);
        }

        usort($clean, fn (array $a, array $b) => $b['min_score'] <=> $a['min_score']);

        return $clean;
    }
}
