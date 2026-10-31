<?php

namespace App\Actions\ParentAuth;

use App\Models\Guardian;
use App\Models\User;
use App\Support\Phone;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Signs a parent in with phone and password, returning a token for this device.
 */
class LoginParent
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private FindParentGuardian $findParentGuardian,
        private IssueParentToken $issueParentToken,
    ) {}

    public function handle(string $phone, string $password, ?string $device = null): string
    {
        $normalised = Phone::normalise($phone);

        // users is not a school-owned model (platform users have no school), so scope by hand.
        $user = $normalised === null ? null : User::where('school_id', $this->currentSchool->require()->id)
            ->where('phone', $normalised)
            ->whereNull('deactivated_at')
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['phone' => 'The phone number or password is wrong.']);
        }

        $guardian = $this->findParentGuardian->handle($normalised);
        if (! $guardian instanceof Guardian || $guardian->user_id !== $user->id) {
            throw FindParentGuardian::noAccess('phone');
        }

        return $this->issueParentToken->handle($user, $device);
    }
}
