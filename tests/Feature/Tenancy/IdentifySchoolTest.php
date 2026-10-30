<?php

use App\Http\Middleware\IdentifySchool;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    // Test-only route: reports what IdentifySchool resolved for this request.
    Route::domain('{school}.'.config('app.base_domain'))
        ->middleware(['web', 'school'])
        ->get('/_tenancy-probe', fn () => [
            'school_id' => app(CurrentSchool::class)->id(),
            'route_parameters' => request()->route()->parameters(),
            'dashboard_url' => route('dashboard'),
        ]);

    $this->greenhill = School::factory()->active()->create(['name' => 'Greenhill Academy', 'subdomain' => 'greenhill']);
    $this->hillview = School::factory()->active()->create(['name' => 'Hillview School', 'subdomain' => 'hillview']);
});

it('resolves the school from the subdomain', function () {
    $this->get('http://greenhill.localhost/_tenancy-probe')
        ->assertOk()
        ->assertJson([
            'school_id' => $this->greenhill->id,
            'route_parameters' => [],
            'dashboard_url' => 'http://greenhill.localhost/dashboard',
        ]);
});

it("shows each school's own name on its login page", function () {
    $this->get('http://greenhill.localhost/login')
        ->assertOk()
        ->assertSee('Greenhill Academy')
        ->assertDontSee('Hillview School');

    $this->get('http://hillview.localhost/login')
        ->assertOk()
        ->assertSee('Hillview School')
        ->assertDontSee('Greenhill Academy');
});

it('sends the school root to its login page', function () {
    $this->get('http://greenhill.localhost/')->assertRedirect('http://greenhill.localhost/login');
});

it('lets a school on trial in', function () {
    School::factory()->create(['subdomain' => 'newschool']);

    $this->get('http://newschool.localhost/login')->assertOk();
});

it('returns 404 for an unknown subdomain', function () {
    $this->get('http://nowhere.localhost/login')->assertNotFound();
    $this->getJson('http://nowhere.localhost/api/user')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('shows the suspended page for a suspended school', function () {
    School::factory()->suspended()->create(['name' => 'Closed Gate School', 'subdomain' => 'closed']);

    $this->get('http://closed.localhost/login')
        ->assertForbidden()
        ->assertSee('Closed Gate School')
        ->assertSee('currently suspended');

    expect(app(CurrentSchool::class)->has())->toBeFalse();
});

it('returns JSON 403 from the API for a suspended school', function () {
    School::factory()->suspended()->create(['subdomain' => 'closed']);

    $this->getJson('http://closed.localhost/api/user')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This school account is suspended.']);
});

it('requires a token on the school API', function () {
    $this->getJson('http://greenhill.localhost/api/user')->assertUnauthorized();
});

it('serves the bare domain without a school', function () {
    $this->get('http://localhost/')->assertOk();

    expect(app(CurrentSchool::class)->has())->toBeFalse();
});

it('does not serve school pages on the bare domain', function () {
    $this->get('http://localhost/login')->assertNotFound();
    $this->get('http://localhost/dashboard')->assertNotFound();
});

it('re-runs on Livewire update requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(IdentifySchool::class);
});
