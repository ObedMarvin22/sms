<?php

use App\Enums\PlatformRoleKey;
use App\Models\School;
use App\Models\User;

it('creates a super admin from the prompts', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Obed')
        ->expectsQuestion('Email', 'Obed@OrangeTree.test')
        ->expectsQuestion('Password (min 12, mixed case, number, symbol)', 'Very-Strong-Pass-1')
        ->expectsChoice('Role', 'super_admin', ['super_admin', 'support', 'Super admin', 'Support'])
        ->assertSuccessful();

    $user = User::where('email', 'obed@orangetree.test')->sole();

    expect($user->school_id)->toBeNull()
        ->and($user->platform_role)->toBe(PlatformRoleKey::SuperAdmin)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('refuses a duplicate platform email but allows the same email at a school', function () {
    User::factory()->for(School::factory())->create(['email' => 'shared@orangetree.test']);
    User::factory()->platform()->create(['email' => 'taken@orangetree.test']);

    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Someone')
        ->expectsQuestion('Email', 'taken@orangetree.test')
        ->expectsQuestion('Password (min 12, mixed case, number, symbol)', 'Very-Strong-Pass-1')
        ->expectsChoice('Role', 'super_admin', ['super_admin', 'support', 'Super admin', 'Support'])
        ->assertFailed();

    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Someone')
        ->expectsQuestion('Email', 'shared@orangetree.test')
        ->expectsQuestion('Password (min 12, mixed case, number, symbol)', 'Very-Strong-Pass-1')
        ->expectsChoice('Role', 'support', ['super_admin', 'support', 'Super admin', 'Support'])
        ->assertSuccessful();
});

it('refuses a weak password', function () {
    $this->artisan('platform:create-admin')
        ->expectsQuestion('Name', 'Someone')
        ->expectsQuestion('Email', 'new@orangetree.test')
        ->expectsQuestion('Password (min 12, mixed case, number, symbol)', 'short')
        ->expectsChoice('Role', 'super_admin', ['super_admin', 'support', 'Super admin', 'Support'])
        ->assertFailed();

    expect(User::where('email', 'new@orangetree.test')->exists())->toBeFalse();
});
