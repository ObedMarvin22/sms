<?php

namespace App\Actions\Saas;

use App\Enums\SchoolStatus;
use App\Models\SaasInvoice;
use App\Models\School;
use App\Models\Term;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bills a school for one term: active students on the census date × its plan's rate, copied onto
 * the invoice. Only active schools are billed (not trial, not suspended), only once the census
 * date has passed, and only once per term (a voided invoice can be replaced).
 */
class IssueSaasInvoice
{
    public function __construct(private CountCensusStudents $count, private NextSaasInvoiceNumber $nextNumber) {}

    public function handle(School $school, Term $term, ?User $by = null): SaasInvoice
    {
        $fail = fn (string $message) => ValidationException::withMessages(['invoice' => $message]);

        if ($term->getAttribute('school_id') !== $school->id) {
            throw $fail('That term belongs to another school.');
        }
        if ($school->status !== SchoolStatus::Active) {
            throw $fail("{$school->name} is not billed while it is {$school->status->label()}.");
        }
        $plan = $school->planModel;
        if ($plan === null) {
            throw $fail("Choose a plan for {$school->name} first.");
        }
        if ($term->census_on->isAfter(today())) {
            throw $fail("{$term->name}'s census date ({$term->census_on->format('j M Y')}) has not come yet.");
        }
        if (SaasInvoice::live()->where('school_id', $school->id)->where('term_id', $term->id)->exists()) {
            throw $fail("{$school->name} already has an invoice for {$term->name}.");
        }

        $students = $this->count->handle($school, $term->census_on);

        try {
            return DB::transaction(function () use ($school, $term, $plan, $students, $by): SaasInvoice {
                $invoice = new SaasInvoice;
                $invoice->forceFill([
                    'number' => $this->nextNumber->handle((int) now()->format('Y')),
                    'school_id' => $school->id,
                    'term_id' => $term->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'rate_per_student' => $plan->rate_per_student,
                    'census_on' => $term->census_on,
                    'student_count' => $students,
                    'amount' => Money::pesewas($plan->rate_per_student->pesewas * $students),
                    'issued_at' => now(),
                    'due_on' => today()->addDays((int) config('saas.due_days')),
                    'issued_by' => $by?->id,
                ])->save();

                return $invoice;
            });
        } catch (UniqueConstraintViolationException) {
            // Two issues at the same moment: the other one won.
            throw $fail("{$school->name} already has an invoice for {$term->name}.");
        }
    }
}
