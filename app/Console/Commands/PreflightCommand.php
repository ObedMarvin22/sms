<?php

namespace App\Console\Commands;

use App\Support\Deployment\Preflight;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:preflight')]
#[Description('Check production/staging settings before a release (errors stop scripts/deploy.sh)')]
class PreflightCommand extends Command
{
    public function handle(): int
    {
        ['errors' => $errors, 'warnings' => $warnings] = Preflight::check();

        foreach ($errors as $message) {
            $this->error('✗ '.$message);
        }
        foreach ($warnings as $message) {
            $this->warn('! '.$message);
        }

        if ($errors !== []) {
            $this->newLine();
            $this->error(count($errors).' problem(s) must be fixed before releasing.');

            return self::FAILURE;
        }

        $this->info('Pre-flight checks passed for '.app()->environment().'.');

        return self::SUCCESS;
    }
}
