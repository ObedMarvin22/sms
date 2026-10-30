<?php

namespace App\Console\Commands;

use App\Actions\Attendance\NotifyTodaysAbsences;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:notify-absences')]
#[Description("Notify guardians of absent students once each school's registers have locked")]
class NotifyAbsencesCommand extends Command
{
    public function handle(CurrentSchool $currentSchool, NotifyTodaysAbsences $notifyTodaysAbsences): int
    {
        $total = 0;
        School::where('status', '!=', SchoolStatus::Suspended->value)->each(function (School $school) use ($currentSchool, $notifyTodaysAbsences, &$total): void {
            $total += $currentSchool->runFor($school, fn (): int => $notifyTodaysAbsences->handle());
        });

        $this->components->info("Notified guardians of {$total} absent students.");

        return self::SUCCESS;
    }
}
