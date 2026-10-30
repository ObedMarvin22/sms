<?php

namespace App\Actions\Guardians;

use App\Models\Guardian;

/**
 * Edits a guardian's own details (shared by all their children).
 */
class UpdateGuardian
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Guardian $guardian, array $input): Guardian
    {
        $guardian->update(GuardianRules::details($input, ignore: $guardian));

        return $guardian;
    }
}
