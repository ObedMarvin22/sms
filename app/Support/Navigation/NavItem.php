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
        public ?PermissionKey $permission = null,
        public ?string $group = null,
    ) {}
}
