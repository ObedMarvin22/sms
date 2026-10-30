<?php

namespace App\Actions\Staff;

use App\Enums\RoleKey;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a staff member's roles in the current school. The actor may only give or remove
 * roles whose permissions they hold themselves. (Changes are logged by LogRoleChanges.)
 */
class SetStaffRoles
{
    /**
     * @param  list<string>  $roleValues  RoleKey values
     */
    public function handle(Staff $staff, array $roleValues, User $actor): Staff
    {
        $user = $staff->user ?? throw ValidationException::withMessages(['roles' => 'Give this person an email (a login) before giving them roles.']);

        $wanted = StaffRules::roles($roleValues);
        $current = array_values(array_filter(array_map(
            fn (string $name) => RoleKey::tryFrom($name),
            $user->getRoleNames()->all(),
        )));

        $added = array_values(array_udiff($wanted, $current, fn (RoleKey $a, RoleKey $b) => strcmp($a->value, $b->value)));
        $removed = array_values(array_udiff($current, $wanted, fn (RoleKey $a, RoleKey $b) => strcmp($a->value, $b->value)));

        // Stops a school locking itself out of its own settings by accident.
        $removesOwnLeadership = $user->is($actor)
            && array_intersect([RoleKey::Proprietor->value, RoleKey::Administrator->value], array_map(fn (RoleKey $r) => $r->value, $removed)) !== [];

        if ($removesOwnLeadership) {
            throw ValidationException::withMessages(['roles' => 'You cannot remove your own leadership role.']);
        }

        StaffRules::assertCanChangeRoles($actor, [...$added, ...$removed]);

        $user->syncRoles(array_map(fn (RoleKey $role) => $role->value, $wanted));

        return $staff;
    }
}
