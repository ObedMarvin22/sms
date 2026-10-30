<?php

use App\Exceptions\NoCurrentSchool;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;

it('is empty until a school is set', function () {
    $current = app(CurrentSchool::class);

    expect($current->has())->toBeFalse()
        ->and($current->get())->toBeNull()
        ->and($current->id())->toBeNull();
});

it('holds the school that was set', function () {
    $school = School::factory()->create();
    $current = app(CurrentSchool::class);

    $current->set($school);

    expect($current->has())->toBeTrue()
        ->and($current->get())->toBe($school)
        ->and($current->id())->toBe($school->id)
        ->and($current->require())->toBe($school);
});

it('fails loudly when a school is required but missing', function () {
    app(CurrentSchool::class)->require();
})->throws(NoCurrentSchool::class);

it('can be cleared', function () {
    $current = app(CurrentSchool::class);
    $current->set(School::factory()->create());

    $current->clear();

    expect($current->has())->toBeFalse();
});

it('returns the same holder throughout one request', function () {
    $school = School::factory()->create();

    app(CurrentSchool::class)->set($school);

    expect(app(CurrentSchool::class))->toBe(app(CurrentSchool::class))
        ->and(app(CurrentSchool::class)->id())->toBe($school->id);
});

it('starts empty again for the next queued job', function () {
    app(CurrentSchool::class)->set(School::factory()->create());

    // The queue worker calls this before every job (QueueServiceProvider::registerWorker).
    app()->forgetScopedInstances();

    expect(app(CurrentSchool::class)->has())->toBeFalse();
});
