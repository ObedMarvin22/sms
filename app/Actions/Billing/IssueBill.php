<?php

namespace App\Actions\Billing;

use App\Enums\LedgerEntryType;
use App\Models\Bill;
use App\Models\BillingRun;
use App\Models\BillInstalment;
use App\Models\BillLine;
use App\Models\LedgerEntry;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Issues a student's bill for a term: the bill, its lines and instalments, and one debit in the
 * ledger, all in one transaction. The student's row is locked first, so two runs billing the
 * same student wait for each other; a student already billed for the term gets their existing
 * bill back (wasRecentlyCreated = false), never a second one.
 *
 * @throws StudentNotBillable
 */
class IssueBill
{
    public function __construct(private BillStudent $billStudent) {}

    public function handle(Student $student, Term $term, ?BillingRun $run = null): Bill
    {
        return DB::transaction(function () use ($student, $term, $run): Bill {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $existing = Bill::where('student_id', $student->id)->where('term_id', $term->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $draft = $this->billStudent->handle($student, $term);

            $bill = Bill::create([
                'student_id' => $student->id,
                'enrolment_id' => $draft->enrolment->id,
                'term_id' => $term->id,
                'school_class_id' => $draft->class->id,
                'residence' => $draft->residence,
                'billing_run_id' => $run?->id,
                'gross' => $draft->gross,
                'reductions' => $draft->reductions,
                'net' => $draft->net,
                'arrears' => $draft->arrears,
                'issued_at' => now(),
            ]);

            foreach ($draft->lines as $i => $line) {
                BillLine::create(['bill_id' => $bill->id, 'sequence' => $i + 1, ...$line]);
            }
            foreach ($draft->instalments as $i => $instalment) {
                BillInstalment::create(['bill_id' => $bill->id, 'sequence' => $i + 1, ...$instalment]);
            }

            if ($draft->net->isPositive()) {
                LedgerEntry::create([
                    'student_id' => $student->id,
                    'type' => LedgerEntryType::Bill,
                    'amount' => $draft->net,
                    'bill_id' => $bill->id,
                    'description' => mb_substr("{$term->name} fees ({$draft->class->name})", 0, 150),
                    'entry_date' => now()->toDateString(),
                    'created_by' => $run?->user_id,
                ]);
            }

            return $bill;
        });
    }
}
