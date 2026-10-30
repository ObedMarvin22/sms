<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Notifications\SchoolInvitation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;

/**
 * Sends the invitation again, while the staff member has not yet set a password.
 */
class ResendInvitation
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Staff $staff): void
    {
        $user = $staff->user;

        if ($user === null) {
            throw ValidationException::withMessages(['invitation' => 'This person has no login. Add an email first.']);
        }
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages(['invitation' => 'This person has already accepted their invitation.']);
        }

        $user->notify(new SchoolInvitation($this->currentSchool->require()));
    }
}
