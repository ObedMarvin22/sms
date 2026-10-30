<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Enums\RoleKey;
use App\Enums\SchoolLevel;
use App\Livewire\School\Profile;
use App\Models\Activity;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');

    $this->greenhill = School::factory()->active()->create(['name' => 'Greenhill Academy', 'subdomain' => 'greenhill']);
    $this->hillview = School::factory()->active()->create(['name' => 'Hillview School', 'subdomain' => 'hillview']);
    app(SeedDefaultRoles::class)->handle($this->greenhill);
    app(SeedDefaultRoles::class)->handle($this->hillview);
});

function withRole(School $school, RoleKey $role): User
{
    $user = User::factory()->for($school)->withTwoFactor()->create();
    app(CurrentSchool::class)->runFor($school, fn () => $user->assignRole($role->value));

    return $user;
}

/**
 * Livewire::test() skips HTTP middleware, so set what IdentifySchool would.
 */
function profileAs(User $user, School $school)
{
    test()->onSchool($school);
    app(CurrentSchool::class)->set($school);

    return Livewire::actingAs($user)->test(Profile::class);
}

it('refuses teachers the page', function (RoleKey $role) {
    $this->actingAs(withRole($this->greenhill, $role))
        ->get('http://greenhill.localhost/settings/school')
        ->assertForbidden();
})->with([RoleKey::ClassTeacher, RoleKey::SubjectTeacher, RoleKey::Bursar, RoleKey::GateStaff]);

it('refuses a teacher calling save directly', function () {
    profileAs(withRole($this->greenhill, RoleKey::ClassTeacher), $this->greenhill)->assertForbidden();
});

it('opens for school leaders', function (RoleKey $role) {
    $this->actingAs(withRole($this->greenhill, $role))
        ->get('http://greenhill.localhost/settings/school')
        ->assertOk()
        ->assertSee('greenhill.localhost');
})->with([RoleKey::Proprietor, RoleKey::Headteacher, RoleKey::Administrator]);

it('saves the profile and logs the change', function () {
    $admin = withRole($this->greenhill, RoleKey::Administrator);

    profileAs($admin, $this->greenhill)
        ->set('name', 'Greenhill International Academy')
        ->set('primary_colour', '#0A7D3C')
        ->set('secondary_colour', '#F5D90A')
        ->set('phone', '024 412 3456')
        ->set('email', ' Office@Greenhill.TEST ')
        ->set('address', 'Box 12, Adenta, Accra')
        ->set('levels', ['kg', 'primary', 'jhs'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('phone', '+233244123456');

    expect($this->greenhill->fresh())
        ->name->toBe('Greenhill International Academy')
        ->primary_colour->toBe('#0A7D3C')
        ->phone->toBe('+233244123456')
        ->email->toBe('office@greenhill.test')
        ->address->toBe('Box 12, Adenta, Accra')
        ->subdomain->toBe('greenhill')
        ->levels_offered->all()->toBe([SchoolLevel::Kg, SchoolLevel::Primary, SchoolLevel::Jhs]);

    $entry = Activity::withoutGlobalScopes()->where('log_name', 'schools')->latest('id')->firstOrFail();
    expect($entry->school_id)->toBe($this->greenhill->id)
        ->and($entry->causer->is($admin))->toBeTrue()
        ->and($entry->attribute_changes['attributes']['name'])->toBe('Greenhill International Academy');
});

it('rejects invalid input', function (string $field, mixed $value) {
    profileAs(withRole($this->greenhill, RoleKey::Administrator), $this->greenhill)
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'colour' => ['primary_colour', 'green'],
    'short colour' => ['secondary_colour', '#0a7'],
    'phone' => ['phone', '+44 20 7946 0958'],
    'email' => ['email', 'not-an-email'],
    'no levels' => ['levels', []],
    'blank name' => ['name', ''],
]);

it("stores the logo privately under the school's folder and replaces the old one", function () {
    $admin = withRole($this->greenhill, RoleKey::Administrator);

    profileAs($admin, $this->greenhill)
        ->set('logo', UploadedFile::fake()->image('crest.png', 200, 200))
        ->call('save')
        ->assertHasNoErrors();

    $first = $this->greenhill->fresh()->logo_path;
    expect($first)->toBe("schools/{$this->greenhill->id}/branding/logo.png");
    Storage::disk('local')->assertExists($first);

    profileAs($admin, $this->greenhill)
        ->set('logo', UploadedFile::fake()->image('crest.jpg', 200, 200))
        ->call('save')
        ->assertHasNoErrors();

    $second = $this->greenhill->fresh()->logo_path;
    expect($second)->toBe("schools/{$this->greenhill->id}/branding/logo.jpg");
    Storage::disk('local')->assertExists($second);
    Storage::disk('local')->assertMissing($first);
});

it('rejects SVG and oversized logos', function (UploadedFile $file) {
    profileAs(withRole($this->greenhill, RoleKey::Administrator), $this->greenhill)
        ->set('logo', $file)
        ->call('save')
        ->assertHasErrors('logo');

    expect($this->greenhill->fresh()->logo_path)->toBeNull();
})->with([
    'svg' => fn () => UploadedFile::fake()->create('crest.svg', 10, 'image/svg+xml'),
    'too big' => fn () => UploadedFile::fake()->image('crest.png')->size(1500),
]);

it("serves each school's logo on its own subdomain only", function () {
    Storage::disk('local')->put("schools/{$this->greenhill->id}/branding/logo.png", 'GREENHILL-LOGO');
    $this->greenhill->update(['logo_path' => "schools/{$this->greenhill->id}/branding/logo.png"]);

    $this->get('http://greenhill.localhost/branding/logo')
        ->assertOk()
        ->assertStreamedContent('GREENHILL-LOGO');

    // Hillview has no logo: its subdomain cannot reach Greenhill's file.
    $this->get('http://hillview.localhost/branding/logo')->assertNotFound();
    $this->get('http://hillview.localhost/branding/logo?school='.$this->greenhill->id)->assertNotFound();
});

it('keeps logos off the public disk', function () {
    Storage::disk('local')->put("schools/{$this->greenhill->id}/branding/logo.png", 'x');

    // /storage/... is Laravel's route for the private disk: it only serves signed temporary URLs,
    // which this app never creates for logos. Without a signature it refuses (403).
    $this->get("http://localhost/storage/schools/{$this->greenhill->id}/branding/logo.png")->assertForbidden();
    expect(file_exists(public_path("storage/schools/{$this->greenhill->id}/branding/logo.png")))->toBeFalse();
});

it('shows the uploaded logo in the sidebar and on the login page', function () {
    Storage::disk('local')->put("schools/{$this->greenhill->id}/branding/logo.png", 'x');
    $this->greenhill->update(['logo_path' => "schools/{$this->greenhill->id}/branding/logo.png"]);

    $this->get('http://greenhill.localhost/login')->assertSee('data-test="school-logo"', false);

    $this->actingAs(withRole($this->greenhill, RoleKey::ClassTeacher))
        ->get('http://greenhill.localhost/dashboard')
        ->assertSee('data-test="school-logo"', false)
        ->assertDontSee('data-test="school-initials"', false);
});

it('lists School profile in the sidebar for leaders only', function () {
    $this->actingAs(withRole($this->greenhill, RoleKey::Administrator))
        ->get('http://greenhill.localhost/dashboard')
        ->assertSee('School profile');

    $this->actingAs(withRole($this->greenhill, RoleKey::ClassTeacher))
        ->get('http://greenhill.localhost/dashboard')
        ->assertDontSee('School profile');
});

it('does not put auth on the domain-less upload route (it would lock out school staff)', function () {
    // The route has no {school}, so the school-scoped provider cannot find staff there.
    expect(config('livewire.temporary_file_upload.middleware'))->not->toContain('auth')
        ->and(config('livewire.temporary_file_upload.middleware'))->not->toContain('auth:web,platform');
});
