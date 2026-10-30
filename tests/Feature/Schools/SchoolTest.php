<?php

use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Database\QueryException;

it('creates a trial school offering Primary and JHS by default', function () {
    $school = School::factory()->create();

    expect($school->fresh())
        ->status->toBe(SchoolStatus::Trial)
        ->levels_offered->all()->toBe([SchoolLevel::Primary, SchoolLevel::Jhs])
        ->settings->toBe([])
        ->subdomain->toMatch('/^[a-z0-9-]+$/');
});

it('defaults to trial status at the database level', function () {
    $school = School::create([
        'name' => 'Bare Minimum School',
        'subdomain' => 'bare-minimum',
        'levels_offered' => [SchoolLevel::Kg],
    ]);

    expect($school->fresh()->status)->toBe(SchoolStatus::Trial);
});

it('casts status, levels and settings', function () {
    $school = School::factory()
        ->active()
        ->withLevels(SchoolLevel::Creche, SchoolLevel::Shs)
        ->create(['settings' => ['positions_enabled' => false]]);

    $fresh = $school->fresh();

    expect($fresh->status)->toBe(SchoolStatus::Active)
        ->and($fresh->levels_offered->all())->toBe([SchoolLevel::Creche, SchoolLevel::Shs])
        ->and($fresh->settings)->toBe(['positions_enabled' => false]);

    $this->assertDatabaseHas('schools', ['id' => $school->id, 'status' => 'active']);
});

it('has a suspended state', function () {
    expect(School::factory()->suspended()->create()->fresh()->status)
        ->toBe(SchoolStatus::Suspended);
});

it('rejects a duplicate subdomain', function () {
    School::factory()->create(['subdomain' => 'greenhill']);

    School::factory()->create(['subdomain' => 'greenhill']);
})->throws(QueryException::class);

it('rejects a duplicate Paystack subaccount code', function () {
    School::factory()->create(['paystack_subaccount_code' => 'ACCT_abc123']);

    School::factory()->create(['paystack_subaccount_code' => 'ACCT_abc123']);
})->throws(QueryException::class);

it('allows many schools without a Paystack subaccount yet', function () {
    School::factory()->count(3)->create(['paystack_subaccount_code' => null]);

    expect(School::whereNull('paystack_subaccount_code')->count())->toBe(3);
});
