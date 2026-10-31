<?php

namespace App\Actions\Payments;

use App\Models\LedgerEntry;
use App\Support\Money\Money;

/**
 * A student's fee statement: every ledger entry, oldest first, with the balance after it.
 */
class BuildStatement
{
    /**
     * @return list<array{entry: LedgerEntry, balance: Money}>
     */
    public function handle(int $studentId): array
    {
        $running = 0;
        $rows = [];
        foreach (LedgerEntry::with('payment')->where('student_id', $studentId)->orderBy('entry_date')->orderBy('id')->get() as $entry) {
            $running += $entry->amount->pesewas;
            $rows[] = ['entry' => $entry, 'balance' => Money::pesewas($running)];
        }

        return $rows;
    }
}
