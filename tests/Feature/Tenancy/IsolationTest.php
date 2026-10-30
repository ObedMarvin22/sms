<?php

use App\Models\AcademicYear;
use App\Models\Programme;
use App\Models\ProgrammeSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/*
| SPEC section 4: every school-owned model gets an isolation test. Add each new model to the
| dataset below with a closure that creates one record for the given school.
*/
dataset('school-owned models', [
    'AcademicYear' => [fn (School $school): Model => AcademicYear::factory()->for($school)->create()],
    'Term' => [fn (School $school): Model => Term::factory()->for(AcademicYear::factory()->for($school))->create()],
    'SchoolClass' => [fn (School $school): Model => SchoolClass::factory()->for($school)->create()],
    'Stream' => [fn (School $school): Model => Stream::factory()->for(SchoolClass::factory()->for($school))->create()],
    'Subject' => [fn (School $school): Model => Subject::factory()->for($school)->create()],
    'Programme' => [fn (School $school): Model => Programme::factory()->for($school)->create()],
    'ProgrammeSubject' => [fn (School $school): Model => ProgrammeSubject::factory()->state(['programme_id' => Programme::factory()->for($school)])->create()],
]);

beforeEach(function () {
    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();
    $this->current = app(CurrentSchool::class);
});

it('is invisible from another school', function (Closure $make) {
    $record = $make($this->schoolA);
    $class = $record::class;

    $this->current->runFor($this->schoolB, function () use ($class, $record) {
        expect($class::find($record->getKey()))->toBeNull()
            ->and($class::whereKey($record->getKey())->exists())->toBeFalse()
            ->and($class::count())->toBe(0);

        expect(fn () => $class::findOrFail($record->getKey()))->toThrow(ModelNotFoundException::class);
    });

    $this->current->runFor($this->schoolA, fn () => expect($class::find($record->getKey()))->not->toBeNull());
})->with('school-owned models');

it('cannot be updated or deleted from another school', function (Closure $make) {
    $record = $make($this->schoolA);
    $class = $record::class;
    $before = $record->fresh()->getAttributes();

    $this->current->runFor($this->schoolB, function () use ($class, $record) {
        expect($class::whereKey($record->getKey())->update(['updated_at' => now()->addYear()]))->toBe(0)
            ->and($class::whereKey($record->getKey())->delete())->toBe(0);
    });

    expect($record->fresh()?->getAttributes())->toBe($before);
})->with('school-owned models');

it('is stamped with the school it was created in', function (Closure $make) {
    $record = $make($this->schoolA);

    expect($record->getAttribute('school_id'))->toBe($this->schoolA->id);
})->with('school-owned models');
