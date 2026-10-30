<?php

namespace App\Actions\Schools;

use App\Models\School;
use App\Support\Settings\SchoolSettings;

/**
 * Records when a school first completed the setup wizard (kept if already set).
 */
class MarkSetupComplete
{
    public function handle(School $school): School
    {
        if (SchoolSettings::for($school)->get('setup_completed_at') === null) {
            $school->settings = [...($school->settings ?? []), 'setup_completed_at' => now()->toIso8601String()];
            $school->save();
        }

        return $school;
    }
}
