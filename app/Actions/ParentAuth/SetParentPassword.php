<?php

namespace App\Actions\ParentAuth;

use App\Enums\RoleKey;
use App\Models\Guardian;
use App\Models\ParentOtp;
use App\Models\User;
use App\Support\Phone;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Checks a texted code and sets the parent's password: on first use this activates the parent
 * app (creates the login and links it to the guardian), later it resets a forgotten password.
 * Returns a token for this device; every older token is ended.
 */
class SetParentPassword
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private FindParentGuardian $findParentGuardian,
        private IssueParentToken $issueParentToken,
    ) {}

    public function handle(string $phone, string $code, string $password, ?string $device = null): string
    {
        $schoolId = $this->currentSchool->require()->id;
        $normalised = Phone::normalise($phone) ?? throw $this->wrongCode();

        // Returns the user, or why not. Wrong tries must be saved, so nothing is thrown inside.
        $result = DB::transaction(function () use ($schoolId, $normalised, $code, $password): User|string {
            $otp = ParentOtp::where('phone', $normalised)->latest('id')->lockForUpdate()->first();
            if ($otp === null || ! $otp->isUsable()) {
                return 'wrong';
            }
            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                return 'wrong';
            }
            $otp->forceFill(['used_at' => now()])->save();

            $guardian = $this->findParentGuardian->handle($normalised);
            if ($guardian === null) {
                return 'no_access';
            }

            return $this->userFor($schoolId, $guardian, $password) ?? 'no_access';
        });

        if (! $result instanceof User) {
            throw $result === 'wrong' ? $this->wrongCode() : FindParentGuardian::noAccess('code');
        }

        return $this->issueParentToken->handle($result, $device);
    }

    /**
     * The guardian's login, created on activation. Null if it has been switched off.
     */
    private function userFor(int $schoolId, Guardian $guardian, string $password): ?User
    {
        // The guardian's own login, or one left with this phone (e.g. a guardian record re-created).
        $user = User::where('school_id', $schoolId)
            ->where(fn ($query) => $query->whereKey($guardian->user_id ?? 0)->orWhere('phone', $guardian->phone))
            ->first();

        if ($user?->deactivated_at !== null) {
            return null;
        }

        if ($user === null) {
            $user = new User(['name' => $guardian->name]);
            $user->forceFill(['school_id' => $schoolId, 'phone' => $guardian->phone]);
        }

        $user->password = $password;
        $user->save();
        $user->tokens()->delete();

        if (! $user->hasRole(RoleKey::Parent->value)) {
            $user->assignRole(RoleKey::Parent->value);
        }

        if ($guardian->user_id !== $user->id) {
            $guardian->user()->associate($user)->save();
        }

        return $user;
    }

    private function wrongCode(): ValidationException
    {
        return ValidationException::withMessages(['code' => 'That code is wrong or has expired. Ask for a new one.']);
    }
}
