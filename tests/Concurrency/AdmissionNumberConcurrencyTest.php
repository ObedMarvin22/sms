<?php

use App\Models\Activity;
use App\Models\AdmissionSequence;
use App\Models\School;
use App\Models\Student;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
| SPEC section 6 / Sprint 3: "concurrent admissions never collide (tested)".
|
| Not in tests/Feature on purpose: Feature tests run inside one rolled-back transaction, which
| would hide a race. Here several real PHP processes admit students into the same school at the
| same time, with real commits, against the test database. Rows are removed afterwards.
*/
uses(TestCase::class);

const WORKERS = 5;
const PER_WORKER = 20;

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);

    $this->school = School::factory()->active()->create([
        'name' => 'Concurrency Test School',
        'subdomain' => 'concurrency-'.uniqid(),
        'settings' => ['admission_prefix' => 'CTS', 'admission_number_format' => '{PREFIX}/{YYYY}/{SEQ:4}'],
    ]);
});

afterEach(function () {
    Student::withoutGlobalScopes()->withTrashed()->where('school_id', $this->school->id)->forceDelete();
    AdmissionSequence::withoutGlobalScopes()->where('school_id', $this->school->id)->delete();
    Activity::withoutGlobalScopes()->where('school_id', $this->school->id)->delete();
    $this->school->delete();
});

it('never gives two concurrent admissions the same number', function () {
    $env = [
        'APP_ENV' => 'testing',
        'DB_DATABASE' => config('database.connections.mysql.database'),
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];

    $processes = [];
    for ($w = 1; $w <= WORKERS; $w++) {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/admit-worker.php'), (string) $this->school->id, (string) PER_WORKER, (string) $w],
            base_path(),
            $env,
            null,
            120,
        );
        $process->start();
        $processes[] = $process;
    }

    $numbers = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        $numbers = [...$numbers, ...array_filter(explode(PHP_EOL, trim($process->getOutput())))];
    }

    $total = WORKERS * PER_WORKER;
    $year = now()->year;
    $expected = array_map(fn (int $n) => sprintf('CTS/%d/%04d', $year, $n), range(1, $total));

    sort($numbers);
    expect($numbers)->toHaveCount($total)
        ->and(array_unique($numbers))->toHaveCount($total)
        ->and($numbers)->toBe($expected);

    expect(Student::withoutGlobalScopes()->where('school_id', $this->school->id)->count())->toBe($total)
        ->and(AdmissionSequence::withoutGlobalScopes()->where('school_id', $this->school->id)->value('last_number'))->toBe($total);
});
