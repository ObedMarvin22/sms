<?php

/*
| Worker for AdmissionNumberConcurrencyTest: admits N students into one school in its own PHP
| process, with real commits, and prints each admission number on its own line.
|
| Usage: php tests/Concurrency/admit-worker.php <school-id> <count> <label> <stream-id>
*/

use App\Actions\Students\AdmitStudent;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$schoolId, $count, $label, $streamId] = [(int) $argv[1], (int) $argv[2], (string) $argv[3], (int) $argv[4]];

$school = School::findOrFail($schoolId);
$app->make(CurrentSchool::class)->set($school);

try {
    for ($i = 1; $i <= $count; $i++) {
        $student = $app->make(AdmitStudent::class)->handle([
            'first_name' => "Worker{$label}",
            'last_name' => "Student{$i}",
            'stream_id' => $streamId,
        ]);

        echo $student->admission_number, PHP_EOL;
    }
} catch (Throwable $e) {
    // Fail loudly: the test checks the exit code and shows stderr.
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
}
