<?php

namespace App\Actions\ParentAuth;

use App\Enums\GuardianAccess;
use App\Models\Guardian;
use App\Support\Phone;
use Illuminate\Validation\ValidationException;

/**
 * The current school's guardian with this phone, if they may use the parent app: at least one
 * child link above "contact only". Null otherwise.
 */
class FindParentGuardian
{
    public function handle(string $phone): ?Guardian
    {
        $normalised = Phone::normalise($phone);
        if ($normalised === null) {
            return null;
        }

        return Guardian::where('phone', $normalised)
            ->whereHas('links', fn ($links) => $links->where('access_level', '!=', GuardianAccess::None->value))
            ->first();
    }

    /**
     * Told only to someone who has proved they own the phone (a right code or password).
     */
    public static function noAccess(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => 'Your school has not given this number access to the parent app. Please contact the school.']);
    }
}
