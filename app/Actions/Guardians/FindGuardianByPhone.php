<?php

namespace App\Actions\Guardians;

use App\Models\Guardian;
use App\Support\Phone;

/**
 * The duplicate check: this school's guardian with this phone, however it was typed
 * (024…, +233 24…, 23324…). Null if none, or if the input is not a Ghana number.
 */
class FindGuardianByPhone
{
    public function handle(?string $phone): ?Guardian
    {
        $normalised = Phone::normalise($phone);

        return $normalised === null ? null : Guardian::where('phone', $normalised)->first();
    }
}
