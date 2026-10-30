<?php

namespace App\Support\Tenancy;

use App\Exceptions\NoCurrentSchool;
use App\Models\School;

/**
 * The school (tenant) for the current request or queued job.
 *
 * Bound as a scoped singleton, so every request and every queued job starts empty.
 * Filled by the IdentifySchool middleware; read by BelongsToSchool and tenant-aware code.
 */
class CurrentSchool
{
    private ?School $school = null;

    public function set(School $school): void
    {
        $this->school = $school;
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function has(): bool
    {
        return $this->school !== null;
    }

    /**
     * Get the school, or fail loudly when tenant-scoped code runs without one.
     *
     * @throws NoCurrentSchool
     */
    public function require(): School
    {
        return $this->school ?? throw NoCurrentSchool::make();
    }

    public function clear(): void
    {
        $this->school = null;
    }
}
