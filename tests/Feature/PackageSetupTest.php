<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel;

it('protects the API user route with Sanctum', function () {
    expect(config('auth.guards'))->toHaveKey('sanctum');

    $this->onSchool();

    $this->getJson('/api/user')->assertUnauthorized();
});

it('scopes permission tables to a school (teams)', function () {
    expect(config('permission.teams'))->toBeTrue()
        ->and(config('permission.column_names.team_foreign_key'))->toBe('school_id')
        ->and(Schema::hasColumn('roles', 'school_id'))->toBeTrue()
        ->and(Schema::hasColumn('model_has_roles', 'school_id'))->toBeTrue()
        ->and(Schema::hasColumn('model_has_permissions', 'school_id'))->toBeTrue();
});

it('writes to the activity log', function () {
    activity()->log('package check');

    $this->assertDatabaseHas('activity_log', ['description' => 'package check']);
});

it('renders a PDF with dompdf', function () {
    $output = Pdf::loadHTML('<p>Orange Tree</p>')->output();

    expect($output)->toStartWith('%PDF');
});

it('resolves the Excel service', function () {
    expect(app('excel'))->toBeInstanceOf(Excel::class);
});

it('registers the backup commands', function () {
    expect(Artisan::all())->toHaveKeys(['backup:run', 'backup:clean']);
});

it('creates the push subscriptions table', function () {
    expect(Schema::hasTable('push_subscriptions'))->toBeTrue();
});
