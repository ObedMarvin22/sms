<?php

namespace App\Console\Commands;

use App\Actions\Saas\RunSaasCensus;
use App\Models\School;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('saas:census {--school= : One school, by subdomain} {--dry-run : Count only; issue nothing}')]
#[Description("Bill schools whose current term's census date has passed (per active student)")]
class SaasCensusCommand extends Command
{
    public function handle(RunSaasCensus $census): int
    {
        $school = null;
        if (is_string($this->option('school'))) {
            $school = School::where('subdomain', $this->option('school'))->first();
            if ($school === null) {
                $this->error("No school with subdomain \"{$this->option('school')}\".");

                return self::FAILURE;
            }
        }

        $rows = $census->handle($school, (bool) $this->option('dry-run'));
        $this->table(['School', 'Term', 'Students', 'Result'], array_map(fn (array $row) => [
            $row['school'], $row['term'] ?? '—', $row['students'] ?? '—', $row['result'],
        ], $rows));

        return self::SUCCESS;
    }
}
