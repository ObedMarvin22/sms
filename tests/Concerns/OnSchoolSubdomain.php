<?php

namespace Tests\Concerns;

use App\Models\School;

/**
 * Runs every test in the class on a fresh school's subdomain.
 *
 * Laravel calls setUpOnSchoolSubdomain() automatically, after RefreshDatabase.
 */
trait OnSchoolSubdomain
{
    protected School $school;

    protected function setUpOnSchoolSubdomain(): void
    {
        $this->school = $this->onSchool();
    }
}
