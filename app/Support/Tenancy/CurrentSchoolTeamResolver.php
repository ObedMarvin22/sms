<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Spatie permission "team" = the current school (config/permission.php team_resolver).
 *
 * Reading CurrentSchool means role checks always use the same school as BelongsToSchool,
 * and reset with it between requests and queued jobs. An explicit setPermissionsTeamId()
 * (used by Spatie's own artisan commands) overrides it until set back to null.
 */
class CurrentSchoolTeamResolver implements PermissionsTeamResolver
{
    private int|string|null $override = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->override = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->override ?? app(CurrentSchool::class)->id();
    }
}
