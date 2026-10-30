<?php

namespace App\Actions\Staff;

use App\Models\User;

/**
 * A staff member accepts their invitation by setting a password.
 *
 * The invitation came to their email, so the address is marked verified.
 * Changing the password also invalidates the invitation link (InvitationLink).
 */
class AcceptInvitation
{
    public function handle(User $user, string $password): User
    {
        $user->password = $password;
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }
}
