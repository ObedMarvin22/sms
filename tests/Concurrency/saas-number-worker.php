<?php

/*
| Worker for SaasInvoiceNumberConcurrencyTest: takes N invoice numbers in its own PHP process,
| each in its own committed transaction, and prints each number on its own line.
|
| Usage: php tests/Concurrency/saas-number-worker.php <year> <count>
*/

use App\Actions\Saas\NextSaasInvoiceNumber;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$year, $count] = [(int) $argv[1], (int) $argv[2]];

try {
    for ($i = 1; $i <= $count; $i++) {
        echo DB::transaction(fn () => $app->make(NextSaasInvoiceNumber::class)->handle($year)), PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
}
