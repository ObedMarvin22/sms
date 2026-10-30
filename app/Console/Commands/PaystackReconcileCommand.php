<?php

namespace App\Console\Commands;

use App\Actions\Paystack\ReconcilePaystack;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('paystack:reconcile')]
#[Description('Check recent Paystack transactions against our records, for every connected school')]
class PaystackReconcileCommand extends Command
{
    public function handle(CurrentSchool $currentSchool, ReconcilePaystack $reconcile): int
    {
        School::whereNotNull('paystack_subaccount_code')->where('status', '!=', SchoolStatus::Suspended->value)
            ->each(function (School $school) use ($currentSchool, $reconcile): void {
                $counts = $currentSchool->runFor($school, fn (): array => $reconcile->handle());
                $this->components->info("{$school->subdomain}: checked {$counts['checked']}, recorded {$counts['recorded']}, flagged {$counts['flagged']}, errors {$counts['errors']}");
            });

        return self::SUCCESS;
    }
}
