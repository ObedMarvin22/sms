<?php

namespace App\Actions\Academic;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Support\Settings\SchoolSettings;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an academic year in the current school with all its terms (or semesters).
 *
 * If the school has no current term yet, the new year's first term becomes current.
 */
class CreateAcademicYear
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private SetCurrentTerm $setCurrentTerm,
    ) {}

    /**
     * @param  list<array{starts_on: ?string, ends_on: ?string, census_on: ?string}>  $terms
     *
     * @throws ValidationException
     */
    public function handle(string $name, ?string $startsOn, ?string $endsOn, array $terms): AcademicYear
    {
        $school = $this->currentSchool->require();
        $period = SchoolSettings::for($school)->academicPeriod();
        $name = trim($name);

        if (preg_match('#^(\d{4})/(\d{4})$#', $name, $m) !== 1 || (int) $m[2] !== (int) $m[1] + 1) {
            throw ValidationException::withMessages(['name' => 'Use the form 2026/2027.']);
        }
        if (AcademicYear::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'This academic year already exists.']);
        }

        $yearStart = AcademicDates::parse($startsOn, 'starts_on');
        $yearEnd = AcademicDates::parse($endsOn, 'ends_on');

        if ($yearEnd->lte($yearStart)) {
            throw ValidationException::withMessages(['ends_on' => 'The end date must be after the start date.']);
        }

        if (AcademicYear::where('starts_on', '<=', $yearEnd)->where('ends_on', '>=', $yearStart)->exists()) {
            throw ValidationException::withMessages(['starts_on' => 'These dates overlap another academic year.']);
        }

        if (count($terms) !== $period->periodsPerYear()) {
            throw ValidationException::withMessages([
                'terms' => "A year needs {$period->periodsPerYear()} ".strtolower($period->label()).'.',
            ]);
        }

        $parsed = [];
        foreach ($terms as $i => $term) {
            $start = AcademicDates::parse($term['starts_on'] ?? null, "terms.{$i}.starts_on");
            $end = AcademicDates::parse($term['ends_on'] ?? null, "terms.{$i}.ends_on");
            $census = AcademicDates::parse($term['census_on'] ?? null, "terms.{$i}.census_on");
            AcademicDates::checkTerm($yearStart, $yearEnd, $start, $end, $census, "terms.{$i}");
            $parsed[] = [$start, $end, $census];
        }
        AcademicDates::checkInOrderWithoutOverlap(array_map(fn (array $t) => [$t[0], $t[1]], $parsed), 'terms');

        return DB::transaction(function () use ($name, $yearStart, $yearEnd, $parsed, $period): AcademicYear {
            $year = AcademicYear::create(['name' => $name, 'starts_on' => $yearStart, 'ends_on' => $yearEnd]);

            foreach ($parsed as $i => [$start, $end, $census]) {
                Term::create([
                    'academic_year_id' => $year->id,
                    'sequence' => $i + 1,
                    'name' => $period->periodName($i + 1),
                    'starts_on' => $start,
                    'ends_on' => $end,
                    'census_on' => $census,
                ]);
            }

            if (! Term::current()->exists()) {
                $this->setCurrentTerm->handle($year->terms()->firstOrFail());
            }

            return $year->load('terms');
        });
    }
}
