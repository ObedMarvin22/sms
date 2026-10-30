<?php

namespace App\Actions\Guardians;

use App\Models\GuardianStudent;

/**
 * Changes how a guardian relates to one child: relationship, access level, pickup.
 */
class UpdateGuardianLink
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(GuardianStudent $link, array $input): GuardianStudent
    {
        $link->update(GuardianRules::link($input));

        return $link;
    }
}
