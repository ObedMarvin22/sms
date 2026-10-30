<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates the login for a staff member who has an email but no user yet.
 * The password is unusable until they accept the invitation (sent by the caller after commit).
 */
class GiveStaffLogin
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Staff $staff): User
    {
        if ($staff->email === null) {
            throw ValidationException::withMessages(['email' => 'An email is needed to give this person a login.']);
        }
        if ($staff->user_id !== null) {
            return $staff->user()->firstOrFail();
        }

        $user = new User([
            'name' => $staff->full_name,
            'email' => $staff->email,
            'password' => Str::random(64),
        ]);
        $user->school_id = $this->currentSchool->require()->id;
        $user->save();

        $staff->user()->associate($user);
        $staff->save();

        return $user;
    }
}
