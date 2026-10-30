<?php

namespace App\Enums;

/**
 * Roles of Orange Tree staff in the console (users.platform_role, school_id null).
 */
enum PlatformRoleKey: string
{
    case SuperAdmin = 'super_admin';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super admin',
            self::Support => 'Support',
        };
    }

    public function canCreateSchools(): bool
    {
        return $this === self::SuperAdmin;
    }
}
