<?php

namespace App\Actions\Billing;

use App\Models\BillingRun;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Bills one chunk of a run's students. Students who cannot be billed (or were billed by someone
 * else meanwhile) are recorded as skipped with the reason. Safe to retry: billed students get
 * their existing bill back, and skips are keyed by student.
 */
class BillRunStudents
{
    public function __construct(private IssueBill $issueBill, private FinishBillingRun $finishBillingRun) {}

    /**
     * @param  list<int>  $studentIds
     */
    public function handle(int $runId, array $studentIds): void
    {
        $run = BillingRun::with('term')->findOrFail($runId);
        $skipped = [];

        foreach (Student::whereIn('id', $studentIds)->orderBy('id')->get() as $student) {
            try {
                $bill = $this->issueBill->handle($student, $run->term, $run);
                if ($bill->billing_run_id !== $run->id) {
                    $skipped[$student->id] = ['name' => $student->full_name, 'reason' => 'Already billed for this term.'];
                }
            } catch (StudentNotBillable $e) {
                $skipped[$student->id] = ['name' => $student->full_name, 'reason' => $e->getMessage()];
            }
        }

        // Students deleted since the run started can never be billed.
        foreach (array_diff($studentIds, Student::whereIn('id', $studentIds)->pluck('id')->map(intval(...))->all()) as $goneId) {
            $skipped[$goneId] = ['name' => "Student #{$goneId}", 'reason' => 'No longer on the roll.'];
        }

        if ($skipped !== []) {
            DB::transaction(function () use ($runId, $skipped): void {
                $run = BillingRun::whereKey($runId)->lockForUpdate()->firstOrFail();
                // Keyed by student id, so a retried chunk overwrites rather than duplicates.
                $run->skipped = array_replace($run->skipped ?? [], $skipped);
                $run->save();
            });
        }

        $this->finishBillingRun->handle($runId);
    }
}
