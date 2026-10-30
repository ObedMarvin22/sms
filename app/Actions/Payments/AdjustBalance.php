<?php

namespace App\Actions\Payments;

use App\Actions\Fees\FeeRules;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Student;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A manual correction to a student's account, with a reason: a charge (adds to what they owe,
 * e.g. "Uniform added") or a credit (takes from it, e.g. "Fees waived by proprietor"). An
 * adjustment entry in the ledger, logged under "payments".
 */
class AdjustBalance
{
    public function handle(User $user, Student $student, string $direction, string $amount, string $reason): LedgerEntry
    {
        if (! in_array($direction, ['charge', 'credit'], true)) {
            throw ValidationException::withMessages(['direction' => 'Choose a charge or a credit.']);
        }
        $money = FeeRules::amount($amount, 'amount');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 140) {
            throw ValidationException::withMessages(['reason' => 'Say what the adjustment is for (up to 140 characters).']);
        }

        return DB::transaction(function () use ($user, $student, $direction, $money, $reason): LedgerEntry {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $entry = LedgerEntry::create([
                'student_id' => $student->id,
                'type' => LedgerEntryType::Adjustment,
                'amount' => $direction === 'charge' ? $money : Money::pesewas(-$money->pesewas),
                'description' => $reason,
                'entry_date' => now()->toDateString(),
                'created_by' => $user->id,
            ]);

            activity('payments')->performedOn($entry)->causedBy($user)->event('balance_adjusted')
                ->withProperties(['student_id' => $student->id, 'direction' => $direction, 'amount' => $money->pesewas, 'reason' => $reason])
                ->log("{$direction} of {$money->format()}");

            return $entry;
        });
    }
}
