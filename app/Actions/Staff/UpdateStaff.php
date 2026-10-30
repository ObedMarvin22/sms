<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Notifications\SchoolInvitation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits a staff member's details. Adding an email to someone without a login gives them one
 * and sends an invitation; changing the email of someone with a login changes their sign-in email.
 */
class UpdateStaff
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private GiveStaffLogin $giveStaffLogin,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Staff $staff, array $input): Staff
    {
        $data = StaffRules::details($input, ignore: $staff);

        if ($data['email'] === null && $staff->user_id !== null) {
            throw ValidationException::withMessages(['email' => 'This person signs in with their email, so it cannot be removed.']);
        }

        $newLogin = DB::transaction(function () use ($staff, $data): bool {
            $staff->update($data);

            if ($staff->user_id === null && $staff->email !== null) {
                $this->giveStaffLogin->handle($staff);

                return true;
            }

            if ($staff->user !== null) {
                $staff->user->forceFill(['name' => $staff->full_name, 'email' => $staff->email])->save();
            }

            return false;
        });

        if ($newLogin) {
            $staff->user?->notify(new SchoolInvitation($this->currentSchool->require()));
        }

        return $staff;
    }
}
