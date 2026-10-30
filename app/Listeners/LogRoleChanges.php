<?php

namespace App\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Writes role assignments and permission grants to the activity log ("roles" log).
 * Spatie fires these events because config/permission.php has events_enabled = true.
 */
class LogRoleChanges
{
    public function roleAttached(RoleAttachedEvent $event): void
    {
        $this->log($event->model, 'role_assigned', 'roles', $this->names(Role::class, $event->rolesOrIds));
    }

    public function roleDetached(RoleDetachedEvent $event): void
    {
        $this->log($event->model, 'role_removed', 'roles', $this->names(Role::class, $event->rolesOrIds));
    }

    public function permissionAttached(PermissionAttachedEvent $event): void
    {
        $this->log($event->model, 'permission_granted', 'permissions', $this->names(Permission::class, $event->permissionsOrIds));
    }

    public function permissionDetached(PermissionDetachedEvent $event): void
    {
        $this->log($event->model, 'permission_revoked', 'permissions', $this->names(Permission::class, $event->permissionsOrIds));
    }

    /**
     * @param  list<string>  $names
     */
    private function log(Model $subject, string $event, string $key, array $names): void
    {
        if ($names === []) {
            return;
        }

        activity('roles')
            ->performedOn($subject)
            ->event($event)
            ->withProperties([$key => $names])
            ->log(str_replace('_', ' ', $event).': '.implode(', ', $names));
    }

    /**
     * Events carry ids or models (see the Spatie event docblocks); turn either into names.
     *
     * @param  class-string<Role|Permission>  $class
     * @return list<string>
     */
    private function names(string $class, mixed $modelsOrIds): array
    {
        $items = $modelsOrIds instanceof Collection ? $modelsOrIds->all() : Arr::wrap($modelsOrIds);

        $ids = array_filter($items, fn (mixed $item) => ! $item instanceof Model);
        $byId = $ids === [] ? collect() : $class::query()->whereKey($ids)->pluck('name', 'id');

        return array_values(array_filter(array_map(
            fn (mixed $item) => $item instanceof Model ? $item->getAttribute('name') : $byId->get($item),
            $items,
        ), is_string(...)));
    }
}
