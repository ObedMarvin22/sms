<?php

namespace App\Support\Settings;

use App\Models\School;

/**
 * Typed access to schools.settings (JSON) with defaults.
 *
 * Each feature adds its keys here when it is built, with a default, so a school that never
 * saved a value still behaves sensibly. Unknown keys in the JSON are kept untouched.
 */
final class SchoolSettings
{
    /** @var array<string, mixed> */
    public const DEFAULTS = [];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private array $values) {}

    public static function for(School $school): self
    {
        return new self(array_replace(self::DEFAULTS, $school->settings ?? []));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
