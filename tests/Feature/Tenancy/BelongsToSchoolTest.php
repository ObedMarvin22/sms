<?php

use App\Exceptions\CrossSchoolWrite;
use App\Exceptions\NoCurrentSchool;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\SchoolScope;
use Tests\Fixtures\Models\TenantNote;

beforeEach(function () {
    TenantNote::createTable();

    $this->schoolA = School::factory()->active()->create();
    $this->schoolB = School::factory()->active()->create();
    $this->current = app(CurrentSchool::class);
});

function noteFor(School $school, string $body): TenantNote
{
    $current = app(CurrentSchool::class);
    $previous = $current->get();
    $current->set($school);

    $note = TenantNote::create(['body' => $body]);

    $previous ? $current->set($previous) : $current->clear();

    return $note;
}

it('fills school_id from the current school', function () {
    $this->current->set($this->schoolA);

    $note = TenantNote::create(['body' => 'Hello']);

    expect($note->school_id)->toBe($this->schoolA->id);
});

it('refuses to create a row when no school is known', function () {
    TenantNote::create(['body' => 'Orphan']);
})->throws(NoCurrentSchool::class);

it('allows an explicit school_id when no school is current (platform code, seeders)', function () {
    $note = TenantNote::create(['school_id' => $this->schoolB->id, 'body' => 'Seeded']);

    expect($note->school_id)->toBe($this->schoolB->id);
});

it('allows an explicit school_id that matches the current school', function () {
    $this->current->set($this->schoolA);

    expect(TenantNote::create(['school_id' => $this->schoolA->id, 'body' => 'Same'])->school_id)
        ->toBe($this->schoolA->id);
});

it('refuses to create a row for another school', function () {
    $this->current->set($this->schoolA);

    TenantNote::create(['school_id' => $this->schoolB->id, 'body' => 'Sneaky']);
})->throws(CrossSchoolWrite::class);

it("hides another school's rows from every query", function () {
    $noteA = noteFor($this->schoolA, 'A only');
    noteFor($this->schoolB, 'B only');

    $this->current->set($this->schoolB);

    expect(TenantNote::count())->toBe(1)
        ->and(TenantNote::all()->pluck('body')->all())->toBe(['B only'])
        ->and(TenantNote::find($noteA->id))->toBeNull()
        ->and(TenantNote::where('body', 'A only')->first())->toBeNull()
        ->and(TenantNote::whereKey($noteA->id)->exists())->toBeFalse();
});

it("cannot update or delete another school's rows", function () {
    $noteA = noteFor($this->schoolA, 'A original');
    $noteB = noteFor($this->schoolB, 'B original');

    $this->current->set($this->schoolB);

    $updated = TenantNote::query()->update(['body' => 'changed by B']);
    $deleted = TenantNote::whereKey($noteA->id)->delete();

    expect($updated)->toBe(1)
        ->and($deleted)->toBe(0);

    $this->assertDatabaseHas('tenant_notes', ['id' => $noteA->id, 'body' => 'A original']);
    $this->assertDatabaseHas('tenant_notes', ['id' => $noteB->id, 'body' => 'changed by B']);
});

it('only deletes the current school\'s rows in a bulk delete', function () {
    noteFor($this->schoolA, 'A keeps');
    noteFor($this->schoolB, 'B goes');

    $this->current->set($this->schoolB);
    TenantNote::query()->delete();

    $this->current->clear();
    expect(TenantNote::pluck('body')->all())->toBe(['A keeps']);
});

it('refuses to move a row to another school', function () {
    $this->current->set($this->schoolA);
    $note = TenantNote::create(['body' => 'Stay put']);

    $note->update(['school_id' => $this->schoolB->id]);
})->throws(CrossSchoolWrite::class);

it('sees every school when no school is current (platform code)', function () {
    noteFor($this->schoolA, 'A');
    noteFor($this->schoolB, 'B');

    expect(TenantNote::count())->toBe(2);
});

it('can bypass the scope only on purpose', function () {
    noteFor($this->schoolA, 'A');
    noteFor($this->schoolB, 'B');

    $this->current->set($this->schoolB);

    expect(TenantNote::withoutGlobalScope(SchoolScope::class)->count())->toBe(2);
});

it('belongs to its school', function () {
    $note = noteFor($this->schoolA, 'Mine');

    expect($note->school->is($this->schoolA))->toBeTrue();
});
