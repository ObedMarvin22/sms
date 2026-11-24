<?php

namespace App\Actions\Results;

use App\Models\School;

/**
 * Turns class and subject positions (1st, 2nd…) on or off for the school's report cards and
 * results. Off by default; positions are always worked out, only their showing changes.
 */
class SetShowPositions
{
    public function handle(School $school, bool $show): School
    {
        $school->settings = [...($school->settings ?? []), 'show_positions' => $show];
        $school->save();

        activity('grades')->performedOn($school)->event('show_positions_set')
            ->withProperties(['show_positions' => $show])->log($show ? 'positions shown' : 'positions hidden');

        return $school;
    }
}
