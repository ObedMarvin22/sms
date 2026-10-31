<?php

namespace App\Actions\ParentAuth;

use App\Models\User;

/**
 * Ends the token this device signed in with. Other devices stay signed in. Only reached through
 * the "parent" middleware, so the user always came with a token.
 */
class LogoutParent
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
