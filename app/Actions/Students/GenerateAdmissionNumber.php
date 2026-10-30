<?php

namespace App\Actions\Students;

use App\Models\AdmissionSequence;
use App\Models\Student;
use App\Support\Students\AdmissionNumberFormat;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Takes the next admission number for the current school (SPEC section 6).
 *
 * The counter row is locked with SELECT ... FOR UPDATE, so concurrent admissions wait their
 * turn and never get the same number. Call it inside the transaction that saves the student,
 * so the lock is held until the student row exists.
 */
class GenerateAdmissionNumber
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(?CarbonImmutable $admittedOn = null): string
    {
        $school = $this->currentSchool->require();
        $format = AdmissionNumberFormat::for($school);
        $year = ($admittedOn ?? CarbonImmutable::now())->year;
        $scope = $format->scope($year);

        // Several attempts: two first-ever admissions for a scope can deadlock while creating the
        // counter row; MySQL then rolls one back and Laravel retries it (only the outermost
        // transaction retries, so callers such as AdmitStudent use attempts too).
        return DB::transaction(function () use ($school, $format, $year, $scope): string {
            $sequence = AdmissionSequence::where('scope', $scope)->lockForUpdate()->first();

            if ($sequence === null) {
                // First number in this scope: create the counter (a racing insert is ignored).
                DB::table('admission_sequences')->insertOrIgnore([
                    'school_id' => $school->id, 'scope' => $scope, 'last_number' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $sequence = AdmissionSequence::where('scope', $scope)->lockForUpdate()->firstOrFail();
            }

            // Skip numbers already used (e.g. typed in by hand or imported).
            do {
                $sequence->last_number++;
                $number = $format->render($year, $sequence->last_number);
            } while (Student::withTrashed()->where('admission_number', $number)->exists());

            $sequence->save();

            return $number;
        }, attempts: 5);
    }
}
