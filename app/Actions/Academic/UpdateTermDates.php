<?php

namespace App\Actions\Academic;

use App\Models\Term;
use Illuminate\Validation\ValidationException;

/**
 * Changes a term's start, end and census dates, keeping it inside its year and in order.
 */
class UpdateTermDates
{
    /**
     * @throws ValidationException
     */
    public function handle(Term $term, ?string $startsOn, ?string $endsOn, ?string $censusOn): Term
    {
        $year = $term->academicYear;
        $start = AcademicDates::parse($startsOn, 'term.starts_on');
        $end = AcademicDates::parse($endsOn, 'term.ends_on');
        $census = AcademicDates::parse($censusOn, 'term.census_on');

        AcademicDates::checkTerm($year->starts_on, $year->ends_on, $start, $end, $census, 'term');

        foreach ($year->terms()->whereKeyNot($term->id)->get() as $other) {
            $isBefore = $other->sequence < $term->sequence;

            if (($isBefore && $start->lte($other->ends_on)) || (! $isBefore && $end->gte($other->starts_on))) {
                throw ValidationException::withMessages(['term.starts_on' => "These dates overlap the {$other->name}."]);
            }
        }

        $term->update(['starts_on' => $start, 'ends_on' => $end, 'census_on' => $census]);

        return $term;
    }
}
