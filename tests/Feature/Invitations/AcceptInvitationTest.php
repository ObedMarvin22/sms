<?php

use App\Actions\Schools\CreateSchool;
use App\Livewire\Invitations\AcceptInvitation;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();

    $this->school = app(CreateSchool::class)->handle('Greenhill Academy', 'greenhill', ['primary'], 'Ama Mensah', 'ama@greenhill.test');
    $this->admin = User::where('email', 'ama@greenhill.test')->sole();

    Notification::assertSentTo($this->admin, SchoolInvitation::class, function (SchoolInvitation $n) {
        $this->url = $n->toMail($this->admin)->actionUrl;

        return true;
    });

    preg_match('#/invitation/\d+/([0-9a-f]+)\?#', $this->url, $m);
    $this->fingerprint = $m[1];
});

function acceptAs(School $school, User $user, string $fingerprint)
{
    test()->onSchool($school);
    app(CurrentSchool::class)->set($school);

    return Livewire::test(AcceptInvitation::class, ['user' => $user, 'fingerprint' => $fingerprint]);
}

it('opens the set-password page from the emailed link', function () {
    $this->get($this->url)->assertOk()->assertSee('Set your password');
});

it('refuses a tampered link', function () {
    $this->get(str_replace($this->fingerprint, str_repeat('0', 64), $this->url))->assertForbidden();
});

it('refuses an expired link', function () {
    $this->travel(8)->days();

    $this->get($this->url)->assertForbidden();
});

it("refuses the link on another school's subdomain", function () {
    School::factory()->active()->create(['subdomain' => 'hillview']);

    $this->get(str_replace('greenhill.localhost', 'hillview.localhost', $this->url))->assertForbidden();
});

it('sets the password, verifies the email and signs the administrator in', function () {
    acceptAs($this->school, $this->admin, $this->fingerprint)
        ->set('password', 'Correct-Horse-9')
        ->set('password_confirmation', 'Correct-Horse-9')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $admin = $this->admin->fresh();
    expect(Hash::check('Correct-Horse-9', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($admin);
});

it('requires a confirmed password', function () {
    acceptAs($this->school, $this->admin, $this->fingerprint)
        ->set('password', 'Correct-Horse-9')
        ->set('password_confirmation', 'something-else')
        ->call('save')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('works only once', function () {
    acceptAs($this->school, $this->admin, $this->fingerprint)
        ->set('password', 'Correct-Horse-9')
        ->set('password_confirmation', 'Correct-Horse-9')
        ->call('save');

    auth()->logout();

    $this->get($this->url)->assertForbidden();
});

it('refuses an invitation for a user of another school', function () {
    $hillview = School::factory()->active()->create(['subdomain' => 'hillview']);

    acceptAs($hillview, $this->admin, $this->fingerprint)->assertNotFound();
});
