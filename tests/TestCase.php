<?php

namespace Tests;

use App\Models\School;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Send the following requests to a school's subdomain ({subdomain}.base_domain).
     *
     * Both route('...') and relative URLs like '/dashboard' then target that school.
     */
    protected function onSchool(?School $school = null): School
    {
        $school ??= School::factory()->active()->create();

        URL::forceRootUrl('http://'.$school->subdomain.'.'.config('app.base_domain'));
        URL::defaults(['school' => $school->subdomain]);

        return $school;
    }

    /**
     * Send the following requests to the bare base domain (no school).
     */
    protected function onBaseDomain(): void
    {
        URL::forceRootUrl('http://'.config('app.base_domain'));
        URL::defaults(['school' => null]);
    }
}
