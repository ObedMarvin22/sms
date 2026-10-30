<?php

namespace App\Support\Tenancy;

/**
 * Subdomains a school may never use.
 */
final class ReservedSubdomains
{
    /** @var list<string> */
    public const ALL = ['admin', 'www', 'api', 'mail', 'app', 'staging', 'test'];

    public static function contains(string $subdomain): bool
    {
        return in_array(strtolower($subdomain), self::ALL, true);
    }
}
