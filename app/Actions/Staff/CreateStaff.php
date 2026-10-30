<?php

namespace App\Actions\Staff;

use App\Models\Staff;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a staff member. With an email they get a login, their roles and an invitation;
 * without one they are a record only (e.g. cleaners), and cannot be given roles.
 */
class CreateStaff
{
    public function __construct(
        private CurrentSchool $currentSchool,
        private GiveStaffLogin $giveStaffLogin,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $roleValues  RoleKey values
     */
    public function handle(array $input, array $roleValues, User $actor): Staff
    {
        $data = StaffRules::details($input);
        $roles = StaffRules::roles($roleValues);

        if ($roles !== []) {
            if ($data['email'] === null) {
                throw ValidationException::withMessages(['email' => 'Staff with a role need an email so they can sign in.']);
            }
            StaffRules::assertCanChangeRoles($actor, $roles);
        }

        [$staff, $user] = DB::transaction(function () use ($data, $roles): array {
            $staff = Staff::create($data);
            $user = $data['email'] !== null ? $this->giveStaffLogin->handle($staff) : null;

            foreach ($roles as $role) {
                $user?->assignRole($role->value);
            }

            return [$staff, $user];
        });

        // After commit: never invite someone whose record was rolled back.
        $user?->notify(new SchoolInvitation($this->currentSchool->require()));

        return $staff;
    }
}
