<?php

use App\Enums\PlatformRoleKey;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSchoolsSeeder;
use Spatie\Permission\Models\Role;

it('creates Greenhill and Hillview with their roles', function () {
    $this->seed(DatabaseSeeder::class);

    expect(School::orderBy('subdomain')->pluck('subdomain')->all())->toBe(['greenhill', 'hillview']);

    School::all()->each(fn (School $school) => expect(Role::where('school_id', $school->id)->count())->toBe(9));
});

it('gives each school an administrator and a class teacher in that school only', function () {
    $this->seed(DatabaseSeeder::class);
    $greenhill = School::where('subdomain', 'greenhill')->sole();
    $hillview = School::where('subdomain', 'hillview')->sole();
    $admin = User::where('email', 'admin@greenhill.test')->sole();
    $teacher = User::where('email', 'teacher@greenhill.test')->sole();

    expect($admin->school_id)->toBe($greenhill->id)
        ->and($admin->email_verified_at)->not->toBeNull();

    app(CurrentSchool::class)->runFor($greenhill, function () use ($admin, $teacher) {
        expect($admin->fresh()->hasRole('administrator'))->toBeTrue()
            ->and($teacher->fresh()->hasRole('class_teacher'))->toBeTrue();
    });

    app(CurrentSchool::class)->runFor($hillview, fn () => expect($admin->fresh()->getRoleNames()->all())->toBe([]));
});

it('lets each administrator sign in on their own subdomain only', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post('http://hillview.localhost/login', ['email' => 'admin@greenhill.test', 'password' => DemoSchoolsSeeder::PASSWORD])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('http://greenhill.localhost/login', ['email' => 'admin@greenhill.test', 'password' => DemoSchoolsSeeder::PASSWORD])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs(User::where('email', 'admin@greenhill.test')->sole());
});

it('creates a console super admin', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', DemoSchoolsSeeder::CONSOLE_ADMIN_EMAIL)->sole();

    expect($admin->school_id)->toBeNull()
        ->and($admin->platform_role)->toBe(PlatformRoleKey::SuperAdmin);
});

it('can run again without creating duplicates', function () {
    $this->seed(DatabaseSeeder::class);
    $counts = [School::count(), User::count(), Role::count()];

    $this->seed(DatabaseSeeder::class);

    expect([School::count(), User::count(), Role::count()])->toBe($counts);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Call the seeder directly: `db:seed` would first ask for confirmation in production,
    // and this checks the seeder's own guard in case it is ever called another way.
    expect(fn () => app()->call([app(DemoSchoolsSeeder::class), 'run']))
        ->toThrow(RuntimeException::class, 'never run in production');

    expect(School::count())->toBe(0);
});
