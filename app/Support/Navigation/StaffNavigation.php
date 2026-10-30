<?php

namespace App\Support\Navigation;

use App\Enums\PermissionKey;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The staff sidebar, defined in one place and filtered per user and school.
 *
 * An item can be listed before its screen exists: it stays hidden until the route is registered.
 */
final class StaffNavigation
{
    /**
     * @param  list<NavItem>  $items
     */
    public function __construct(private array $items) {}

    public static function default(): self
    {
        return new self([
            new NavItem('Dashboard', 'home', 'dashboard'),

            // Sprint 2: school setup (route names fixed as each screen is built).
            new NavItem('Setup checklist', 'clipboard-document-check', 'setup', PermissionKey::SettingsManage, 'School setup'),
            new NavItem('School profile', 'building-library', 'school.profile', PermissionKey::SettingsManage, 'School setup'),
            new NavItem('Academic years', 'calendar-days', 'academic-years.index', PermissionKey::SettingsManage, 'School setup'),
            new NavItem('Classes', 'rectangle-group', 'classes.index', PermissionKey::SettingsManage, 'School setup'),
            new NavItem('Subjects', 'book-open', 'subjects.index', PermissionKey::SettingsManage, 'School setup'),
            new NavItem('Class subjects', 'table-cells', 'class-subjects.index', PermissionKey::SettingsManage, 'School setup'),
        ]);
    }

    /**
     * Items this user may see in the current school, grouped by heading (null = no heading).
     *
     * @return array<string, list<NavItem>> heading => items ('' for ungrouped)
     */
    public function forUser(?User $user): array
    {
        $groups = [];

        foreach ($this->items as $item) {
            if (! Route::has($item->route)) {
                continue;
            }

            if ($item->permission !== null && ! $user?->can($item->permission->value)) {
                continue;
            }

            $groups[$item->group ?? ''][] = $item;
        }

        return $groups;
    }
}
