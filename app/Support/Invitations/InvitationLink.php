<?php

namespace App\Support\Invitations;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Signed, single-use link for a staff member to set their first password.
 *
 * Signed: the URL (host included) cannot be altered and expires after VALID_DAYS.
 * Single use: it carries a fingerprint of the current password hash, so it stops
 * working as soon as the password is set. No extra table is needed.
 */
final class InvitationLink
{
    public const VALID_DAYS = 7;

    public static function url(User $user, School $school): string
    {
        return URL::temporarySignedRoute('invitation.accept', now()->addDays(self::VALID_DAYS), [
            'school' => $school->subdomain,
            'user' => $user->id,
            'fingerprint' => self::fingerprint($user),
        ]);
    }

    public static function isUnused(User $user, string $fingerprint): bool
    {
        return hash_equals(self::fingerprint($user), $fingerprint);
    }

    private static function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->getAuthPassword(), (string) config('app.key'));
    }
}
