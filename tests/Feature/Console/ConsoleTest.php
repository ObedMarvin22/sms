<?php

use App\Enums\PlatformRoleKey;
use App\Livewire\Console\Login;
use App\Livewire\Console\Schools\Create;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->superAdmin = User::factory()->platform(PlatformRoleKey::SuperAdmin)->create(['email' => 'boss@orangetree.test']);
    $this->support = User::factory()->platform(PlatformRoleKey::Support)->create();
});

it('sends guests to the console login', function () {
    $this->get('http://admin.localhost/schools')->assertRedirect('http://admin.localhost/login');
    $this->get('http://admin.localhost/login')->assertOk()->assertSee('Orange Tree console');
});

it('is never treated as a school, even if a school row says "admin"', function () {
    School::factory()->create(['subdomain' => 'admin']);

    $this->get('http://admin.localhost/login')->assertOk()->assertSee('Orange Tree console');
});

it('logs a platform user in', function () {
    Livewire::test(Login::class)
        ->set('email', 'boss@orangetree.test')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('console.schools.index'));

    $this->assertAuthenticatedAs($this->superAdmin, 'platform');
});

it('refuses school staff at the console', function () {
    $staff = User::factory()->for(School::factory())->create(['email' => 'staff@school.test']);

    Livewire::test(Login::class)
        ->set('email', $staff->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest('platform');
});

it('keeps school sessions and console sessions apart', function () {
    $staff = User::factory()->for(School::factory()->active()->create(['subdomain' => 'greenhill']))->create();

    $this->actingAs($staff, 'web')
        ->get('http://admin.localhost/schools')
        ->assertRedirect('http://admin.localhost/login');

    $this->actingAs($this->superAdmin, 'platform')
        ->get('http://greenhill.localhost/dashboard')
        ->assertRedirect('http://greenhill.localhost/login');
});

it('lists schools', function () {
    School::factory()->create(['name' => 'Greenhill Academy']);
    School::factory()->create(['name' => 'Hillview School']);

    $this->actingAs($this->support, 'platform')
        ->get('http://admin.localhost/schools')
        ->assertOk()
        ->assertSee('Greenhill Academy')
        ->assertSee('Hillview School')
        ->assertDontSee('Create school');
});

it('lets only super admins create schools', function () {
    $this->actingAs($this->support, 'platform')
        ->get('http://admin.localhost/schools/create')
        ->assertForbidden();

    $this->actingAs($this->superAdmin, 'platform')
        ->get('http://admin.localhost/schools/create')
        ->assertOk();
});

it('creates a school from the form', function () {
    Notification::fake();

    Livewire::actingAs($this->superAdmin, 'platform')
        ->test(Create::class)
        ->set('name', 'Greenhill Academy')
        ->set('subdomain', 'greenhill')
        ->set('levels', ['kg', 'primary'])
        ->set('adminName', 'Ama Mensah')
        ->set('adminEmail', 'ama@greenhill.test')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('console.schools.index'));

    expect(School::where('subdomain', 'greenhill')->exists())->toBeTrue();
});

it('shows Action validation errors on the right fields', function () {
    Livewire::actingAs($this->superAdmin, 'platform')
        ->test(Create::class)
        ->set('name', 'Bad School')
        ->set('subdomain', 'admin')
        ->set('levels', [])
        ->set('adminName', 'Ama')
        ->set('adminEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['subdomain', 'levels', 'adminEmail']);

    expect(School::count())->toBe(0);
});

it('stops support staff from creating a school even by calling save directly', function () {
    Livewire::actingAs($this->support, 'platform')
        ->test(Create::class)
        ->assertForbidden();
});
