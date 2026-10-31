<?php

namespace App\Actions\Saas;

use App\Enums\SchoolStatus;
use App\Models\SaasInvoice;
use App\Models\School;
use App\Models\Term;
use Illuminate\Validation\ValidationException;

/**
 * The daily census run: bills every active school whose current term's census date has passed
 * and has no invoice yet, so a missed day is caught up the next. A dry run only counts.
 */
class RunSaasCensus
{
    public function __construct(private CountCensusStudents $count, private IssueSaasInvoice $issue) {}

    /**
     * @return list<array{school: string, term: string|null, students: int|null, result: string}>
     */
    public function handle(?School $only = null, bool $dryRun = false): array
    {
        $rows = [];
        $schools = $only !== null ? collect([$only]) : School::whereIn('status', [SchoolStatus::Active->value, SchoolStatus::Trial->value])->orderBy('name')->get();

        foreach ($schools as $school) {
            // Platform code: each school's current term is looked up by its own school_id.
            $term = Term::withoutGlobalScopes()->where('school_id', $school->id)->where('is_current', true)->first();

            if ($term === null || $term->census_on->isAfter(today())) {
                $rows[] = ['school' => $school->name, 'term' => $term?->name, 'students' => null, 'result' => 'census date not reached'];

                continue;
            }
            if (SaasInvoice::live()->where('school_id', $school->id)->where('term_id', $term->id)->exists()) {
                $rows[] = ['school' => $school->name, 'term' => $term->name, 'students' => null, 'result' => 'already invoiced'];

                continue;
            }

            $students = $this->count->handle($school, $term->census_on);

            if ($dryRun) {
                $rows[] = ['school' => $school->name, 'term' => $term->name, 'students' => $students, 'result' => 'dry run: not issued'];

                continue;
            }
            if ($school->status === SchoolStatus::Trial) {
                $rows[] = ['school' => $school->name, 'term' => $term->name, 'students' => $students, 'result' => 'on trial: not billed'];

                continue;
            }

            try {
                $invoice = $this->issue->handle($school, $term);
                $rows[] = ['school' => $school->name, 'term' => $term->name, 'students' => $students, 'result' => "issued {$invoice->number} for {$invoice->amount->format()}"];
            } catch (ValidationException $e) {
                $rows[] = ['school' => $school->name, 'term' => $term->name, 'students' => $students, 'result' => 'not issued: '.$e->validator->errors()->first()];
            }
        }

        return $rows;
    }
}
