<?php

namespace App\Actions\Billing;

use App\Enums\BillingRunStatus;
use App\Models\Bill;
use App\Models\BillingRun;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Marks a run finished once every student in it is billed or skipped, records the total and
 * logs it. Called after every chunk; the last one wins.
 */
class FinishBillingRun
{
    public function handle(int $runId): void
    {
        DB::transaction(function () use ($runId): void {
            $run = BillingRun::whereKey($runId)->lockForUpdate()->first();
            if ($run === null || $run->status === BillingRunStatus::Completed) {
                return;
            }

            $billed = $run->billedCount();
            if ($billed + $run->skippedCount() < $run->total_students) {
                return;
            }

            $total = Money::pesewas((int) Bill::where('billing_run_id', $run->id)->sum('net'));
            $run->forceFill(['status' => BillingRunStatus::Completed, 'total_net' => $total, 'completed_at' => now()])->save();

            activity('fees')->performedOn($run)->causedBy($run->user_id === null ? null : $run->user)->event('billing_finished')
                ->withProperties(['term_id' => $run->term_id, 'billed' => $billed, 'skipped' => $run->skippedCount(), 'total_net' => $total->pesewas])
                ->log("billed {$billed} students, {$total->format()}");
        });
    }
}
