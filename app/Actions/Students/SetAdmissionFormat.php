<?php

namespace App\Actions\Students;

use App\Models\School;
use App\Support\Students\AdmissionNumberFormat;
use Illuminate\Validation\ValidationException;

/**
 * Sets a school's admission number prefix and format. Existing numbers are not changed.
 */
class SetAdmissionFormat
{
    public function handle(School $school, string $prefix, string $format): School
    {
        $prefix = mb_strtoupper(trim($prefix));
        $format = trim($format);

        if (preg_match('/^[A-Z0-9-]{1,10}$/', $prefix) !== 1) {
            throw ValidationException::withMessages(['admission_prefix' => 'Use up to 10 letters, numbers or hyphens.']);
        }
        if (($problem = AdmissionNumberFormat::problem($format)) !== null) {
            throw ValidationException::withMessages(['admission_number_format' => $problem]);
        }

        $school->settings = [...($school->settings ?? []), 'admission_prefix' => $prefix, 'admission_number_format' => $format];
        $school->save();

        activity('schools')->performedOn($school)->event('admission_format_set')
            ->withProperties(['prefix' => $prefix, 'format' => $format])->log('admission number format set');

        return $school;
    }
}
