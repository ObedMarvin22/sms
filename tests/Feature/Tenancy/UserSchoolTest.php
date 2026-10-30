<?php

use App\Http\Middleware\EnsureUserBelongsToSchool;
use App\Livewire\Settings\Profile;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->greenhill = School::factory()->active()->create(['subdomain' => 'greenhill']);
    $this->hillview = School::factory()->active()->create(['subdomain' => 'hillview']);
});

function staff(School $school, array $attributes = []): User
{
    return User::factory()->for($school)->create($attributes);
}

it('allows the same email once per school', function () {
    staff($this->greenhill, ['email' => 'ama@example.com']);
    staff($this->hillview, ['email' => 'ama@example.com']);

    expect(User::where('email', 'ama@example.com')->count())->toBe(2);
});

it('rejects a duplicate email within one school', function () {
    staff($this->greenhill, ['email' => 'ama@example.com']);
    staff($this->greenhill, ['email' => 'ama@example.com']);
})->throws(QueryException::class);

it('logs a user in on their own school', function () {
    $user = staff($this->greenhill);

    $this->post('http://greenhill.localhost/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('refuses a user on another school with the same password', function () {
    $user = staff($this->greenhill);

    $this->post('http://hillview.localhost/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs the same email into the right account on each school', function () {
    $atGreenhill = staff($this->greenhill, ['email' => 'kofi@example.com', 'password' => 'green-pass']);
    $atHillview = staff($this->hillview, ['email' => 'kofi@example.com', 'password' => 'hill-pass']);

    $this->post('http://hillview.localhost/login', ['email' => 'kofi@example.com', 'password' => 'hill-pass'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($atHillview);

    // Greenhill's password does not open the Hillview account.
    Auth::logout();
    $this->post('http://hillview.localhost/login', ['email' => 'kofi@example.com', 'password' => 'green-pass'])
        ->assertSessionHasErrors('email');

    expect($atGreenhill->exists)->toBeTrue();
});

it('does not let a platform user log in on a school', function () {
    $platformUser = User::factory()->platform()->create();

    $this->post('http://greenhill.localhost/login', ['email' => $platformUser->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it("logs out a session used on another school's subdomain", function () {
    $user = staff($this->greenhill);

    $this->actingAs($user)
        ->get('http://hillview.localhost/dashboard')
        ->assertRedirect('http://hillview.localhost/login');

    $this->assertGuest();
});

it('keeps a session on its own school', function () {
    $this->actingAs(staff($this->greenhill))
        ->get('http://greenhill.localhost/dashboard')
        ->assertOk();
});

it("rejects an API token on another school's subdomain", function () {
    $token = staff($this->greenhill)->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://hillview.localhost/api/user')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This account does not belong to this school.']);
});

it('accepts an API token on its own school', function () {
    $user = staff($this->greenhill);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://greenhill.localhost/api/user')
        ->assertOk()
        ->assertJson(['id' => $user->id]);
});

it('only sends password reset links for users of that school', function () {
    Notification::fake();
    $user = staff($this->greenhill);

    $this->post('http://hillview.localhost/forgot-password', ['email' => $user->email]);
    Notification::assertNothingSent();

    $this->post('http://greenhill.localhost/forgot-password', ['email' => $user->email]);
    Notification::assertSentTo($user, ResetPassword::class);
});

it('only finds platform users when no school is current', function () {
    staff($this->greenhill, ['email' => 'same@example.com']);
    $platformUser = User::factory()->platform()->create(['email' => 'same@example.com']);

    $found = Auth::createUserProvider('users')->retrieveByCredentials(['email' => 'same@example.com']);

    expect($found?->is($platformUser))->toBeTrue();
});

it('has no public registration or passkey sign-in', function () {
    $this->get('http://greenhill.localhost/register')->assertNotFound();

    $this->get('http://greenhill.localhost/login')
        ->assertOk()
        ->assertDontSee('Sign up')
        ->assertDontSee('Sign in with a passkey');
});

it('checks profile email uniqueness within the school only', function () {
    $user = staff($this->greenhill);
    staff($this->hillview, ['email' => 'taken-at-hillview@example.com']);
    staff($this->greenhill, ['email' => 'taken-at-greenhill@example.com']);

    // Livewire::test() skips the HTTP middleware, so set what IdentifySchool would.
    $this->onSchool($this->greenhill);
    app(CurrentSchool::class)->set($this->greenhill);

    Livewire::actingAs($user)->test(Profile::class)
        ->set('email', 'taken-at-hillview@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('email', 'taken-at-greenhill@example.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email' => 'unique']);
});

it('re-checks membership on Livewire update requests', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureUserBelongsToSchool::class);
});
