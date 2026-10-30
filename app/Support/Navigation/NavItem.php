<?php

namespace App\Support\Navigation;

use App\Enums\PermissionKey;

/**
 * One sidebar link. Shown only when its route exists and the user holds its permission.
 */
final readonly class NavItem
{
    public function __construct(
        public string $label,
        public string $icon,
        public string $route,
        /** A permission, or the name of a Gate ability combining several (e.g. "view-payments"). */
        public PermissionKey|string|null $permission = null,
        public ?string $group = null,
    ) {}
}
