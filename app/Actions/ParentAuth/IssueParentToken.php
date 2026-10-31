<?php

namespace App\Actions\ParentAuth;

use App\Models\User;

/**
 * A Sanctum token for one device of the parent app. It ends 90 days after it was issued;
 * signing out ends it at once, and setting a new password ends all of them.
 */
class IssueParentToken
{
    public const DAYS = 90;

    public const ABILITY = 'parent';

    public function handle(User $user, ?string $device): string
    {
        $name = trim((string) $device) === '' ? 'Parent app' : mb_substr(trim((string) $device), 0, 100);

        return $user->createToken($name, [self::ABILITY], now()->addDays(self::DAYS))->plainTextToken;
    }
}
