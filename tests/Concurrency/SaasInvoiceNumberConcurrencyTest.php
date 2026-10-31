<?php

use App\Models\SaasInvoiceSequence;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
| Sprint 4 step 9: Orange Tree invoice numbers are one series across all schools, so numbers
| taken at the same moment must never collide or skip. Real processes with real commits (see
| AdmissionNumberConcurrencyTest). A far-future year keeps clear of real series; its row is
| removed afterwards.
*/
uses(TestCase::class);

const SAAS_WORKERS = 5;
const SAAS_PER_WORKER = 20;
const SAAS_TEST_YEAR = 2999;

beforeEach(function () {
    Artisan::call('migrate', ['--force' => true]);
    SaasInvoiceSequence::where('year', SAAS_TEST_YEAR)->delete();
});

afterEach(function () {
    SaasInvoiceSequence::where('year', SAAS_TEST_YEAR)->delete();
});

it('never gives two concurrent invoices the same number', function () {
    $env = [
        'APP_ENV' => 'testing',
        'DB_DATABASE' => config('database.connections.mysql.database'),
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];

    $processes = [];
    for ($w = 1; $w <= SAAS_WORKERS; $w++) {
        $process = new Process([PHP_BINARY, base_path('tests/Concurrency/saas-number-worker.php'), (string) SAAS_TEST_YEAR, (string) SAAS_PER_WORKER], base_path(), $env, null, 120);
        $process->start();
        $processes[] = $process;
    }

    $numbers = [];
    foreach ($processes as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        $numbers = [...$numbers, ...array_filter(explode(PHP_EOL, trim($process->getOutput())))];
    }

    $total = SAAS_WORKERS * SAAS_PER_WORKER;
    sort($numbers);

    expect($numbers)->toBe(array_map(fn (int $n) => sprintf('OT/INV/%d/%04d', SAAS_TEST_YEAR, $n), range(1, $total)));
});
