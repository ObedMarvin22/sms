<?php

namespace App\Actions\Roles;

use App\Enums\PermissionKey;
use App\Enums\RoleKey;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the default school roles (RoleKey) with their default permissions.
 *
 * Safe to run again: missing permissions and roles are added, but an existing role's
 * permissions are left alone, because schools may have edited them.
 */
class SeedDefaultRoles
{
    private const GUARD = 'web';

    public function __construct(
        private CurrentSchool $currentSchool,
        private PermissionRegistrar $permissions,
    ) {}

    public function handle(School $school): void
    {
        DB::transaction(function () use ($school): void {
            foreach (PermissionKey::cases() as $permission) {
                Permission::findOrCreate($permission->value, self::GUARD);
            }

            // Roles are created in the school's team (CurrentSchoolTeamResolver).
            $this->currentSchool->runFor($school, function (): void {
                foreach (RoleKey::cases() as $key) {
                    $role = Role::findOrCreate($key->value, self::GUARD);

                    if ($role->wasRecentlyCreated) {
                        $role->givePermissionTo(array_map(
                            fn (PermissionKey $permission) => $permission->value,
                            $key->defaultPermissions(),
                        ));
                    }
                }
            });
        });

        $this->permissions->forgetCachedPermissions();
    }
}
