<?php

namespace App\Actions\Billing;

use App\Enums\BillingRunStatus;
use App\Jobs\BillStudentsChunk;
use App\Models\BillingRun;
use App\Models\Term;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts billing a term: every active, enrolled student without a bill for it, queued
 * CHUNK at a time (cron runs the queue; SPEC section 3). One run per term at a time.
 */
class StartTermBilling
{
    public const CHUNK = 50;

    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(User $user, Term $term): BillingRun
    {
        TermBilling::assertBillable($term);

        /** The student list is taken once, so the run's total always matches what is queued. */
        [$run, $studentIds] = DB::transaction(function () use ($user, $term): array {
            // Lock the term row so two clicks cannot start two runs.
            Term::whereKey($term->id)->lockForUpdate()->firstOrFail();

            if (BillingRun::where('term_id', $term->id)->where('status', BillingRunStatus::Queued->value)->exists()) {
                throw ValidationException::withMessages(['term' => "{$term->name} is already being billed. Wait for it to finish."]);
            }

            $studentIds = array_values(array_map(intval(...), TermBilling::unbilled($term)->orderBy('id')->pluck('id')->all()));
            if ($studentIds === []) {
                throw ValidationException::withMessages(['term' => "Every student already has a bill for {$term->name}."]);
            }

            $run = new BillingRun(['term_id' => $term->id]);
            $run->forceFill(['user_id' => $user->id, 'total_students' => count($studentIds)])->save();

            activity('fees')->performedOn($run)->causedBy($user)->event('billing_started')
                ->withProperties(['term_id' => $term->id, 'students' => count($studentIds)])->log("billing started for {$term->name}");

            return [$run, $studentIds];
        });

        $schoolId = $this->currentSchool->require()->id;
        foreach (array_chunk($studentIds, self::CHUNK) as $chunk) {
            BillStudentsChunk::dispatch($schoolId, $run->id, $chunk);
        }

        return $run;
    }
}
