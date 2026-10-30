<?php

use App\Actions\Roles\SeedDefaultRoles;
use App\Actions\Schools\CreateSchool;
use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Notification::fake());

function createSchool(array $overrides = []): School
{
    $input = array_merge([
        'name' => 'Greenhill Academy',
        'subdomain' => 'greenhill',
        'levels' => ['primary', 'jhs'],
        'adminName' => 'Ama Mensah',
        'adminEmail' => 'ama@greenhill.test',
    ], $overrides);

    return app(CreateSchool::class)->handle(...$input);
}

it('creates a trial school with its levels', function () {
    $school = createSchool();

    expect($school->fresh())
        ->name->toBe('Greenhill Academy')
        ->subdomain->toBe('greenhill')
        ->status->toBe(SchoolStatus::Trial)
        ->levels_offered->all()->toBe([SchoolLevel::Primary, SchoolLevel::Jhs]);
});

it('seeds the nine default roles for the new school', function () {
    $school = createSchool();

    expect(Role::where('school_id', $school->id)->count())->toBe(9);
});

it('creates the first administrator in that school only', function () {
    $school = createSchool();
    $admin = User::where('email', 'ama@greenhill.test')->sole();

    expect($admin->school_id)->toBe($school->id)
        ->and($admin->email_verified_at)->toBeNull();

    app(CurrentSchool::class)->runFor($school, fn () => expect($admin->fresh()->hasRole('administrator'))->toBeTrue());

    $other = School::factory()->create();
    app(CurrentSchool::class)->runFor($other, fn () => expect($admin->fresh()->hasRole('administrator'))->toBeFalse());
});

it("emails the administrator an invitation on the school's subdomain", function () {
    createSchool();
    $admin = User::where('email', 'ama@greenhill.test')->sole();

    Notification::assertSentTo($admin, SchoolInvitation::class, function (SchoolInvitation $notification) use ($admin) {
        $url = $notification->toMail($admin)->actionUrl;

        return str_starts_with($url, 'http://greenhill.localhost/invitation/'.$admin->id.'/')
            && str_contains($url, 'signature=');
    });
});

it('normalises the subdomain and email', function () {
    $school = createSchool(['subdomain' => '  GreenHill ', 'adminEmail' => ' Ama@Greenhill.TEST ']);

    expect($school->subdomain)->toBe('greenhill')
        ->and(User::where('email', 'ama@greenhill.test')->exists())->toBeTrue();
});

it('rejects reserved subdomains', function (string $subdomain) {
    expect(fn () => createSchool(['subdomain' => $subdomain]))
        ->toThrow(ValidationException::class, 'reserved');

    expect(School::count())->toBe(0);
    Notification::assertNothingSent();
})->with(['admin', 'www', 'api', 'mail']);

it('rejects badly formed subdomains', function (string $subdomain) {
    expect(fn () => createSchool(['subdomain' => $subdomain]))->toThrow(ValidationException::class);

    expect(School::count())->toBe(0);
})->with(['ab', '-greenhill', 'greenhill-', 'green hill', 'green_hill', 'grëenhill', str_repeat('a', 64)]);

it('rejects a subdomain that is already taken', function () {
    School::factory()->create(['subdomain' => 'greenhill']);

    expect(fn () => createSchool())->toThrow(ValidationException::class);
    expect(School::count())->toBe(1);
});

it('requires at least one valid level', function () {
    expect(fn () => createSchool(['levels' => []]))->toThrow(ValidationException::class);
    expect(fn () => createSchool(['levels' => ['university']]))->toThrow(ValidationException::class);
});

it('creates nothing and sends nothing when a step fails', function () {
    $this->mock(SeedDefaultRoles::class)
        ->shouldReceive('handle')
        ->andThrow(new RuntimeException('roles failed'));

    expect(fn () => createSchool())->toThrow(RuntimeException::class, 'roles failed');

    expect(School::count())->toBe(0)
        ->and(User::count())->toBe(0);
    Notification::assertNothingSent();
});
