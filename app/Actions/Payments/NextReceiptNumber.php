<?php

namespace App\Actions\Payments;

use App\Models\ReceiptSequence;
use App\Support\Students\AdmissionNumberFormat;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Takes the next receipt number for the current school: "{PREFIX}/R/{year}/{00001}", using the
 * prefix admission numbers use. The counter row is locked (SELECT ... FOR UPDATE), so payments
 * recorded at the same moment wait their turn. Call it inside the transaction that saves the
 * payment; a number is never reused, even if the payment is later reversed.
 */
class NextReceiptNumber
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(int $year): string
    {
        $school = $this->currentSchool->require();

        $sequence = ReceiptSequence::where('year', $year)->lockForUpdate()->first();
        if ($sequence === null) {
            // First receipt this year: create the counter (a racing insert is ignored).
            DB::table('receipt_sequences')->insertOrIgnore([
                'school_id' => $school->id, 'year' => $year, 'last_number' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $sequence = ReceiptSequence::where('year', $year)->lockForUpdate()->firstOrFail();
        }

        $sequence->last_number++;
        $sequence->save();

        $prefix = AdmissionNumberFormat::for($school)->prefix;

        return sprintf('%s/R/%d/%05d', $prefix, $year, $sequence->last_number);
    }
}
