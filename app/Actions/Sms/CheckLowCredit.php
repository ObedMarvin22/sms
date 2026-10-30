<?php

namespace App\Actions\Sms;

use App\Enums\PermissionKey;
use App\Models\MessageCreditEntry;
use App\Models\School;
use App\Models\User;
use App\Notifications\LowSmsCredit;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Warns the school's finance staff (fees.manage) once when SMS credit falls below the threshold,
 * and re-arms the warning once credit is back above it. The "warned" flag lives in settings.
 */
class CheckLowCredit
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(): void
    {
        $schoolId = $this->currentSchool->require()->id;

        $warn = DB::transaction(function () use ($schoolId): ?int {
            $school = School::whereKey($schoolId)->lockForUpdate()->firstOrFail();
            $balance = MessageCreditEntry::balance();
            $low = $balance < SmsSettings::threshold($school);
            $warned = (bool) ($school->settings['sms_low_credit_warned'] ?? false);

            if ($low === $warned) {
                return null;
            }

            $school->settings = [...($school->settings ?? []), 'sms_low_credit_warned' => $low];
            $school->save();

            return $low ? $balance : null;
        });

        if ($warn !== null) {
            $staff = User::where('school_id', $schoolId)->get()
                ->filter(fn (User $user) => $user->can(PermissionKey::FeesManage->value));
            Notification::send($staff, new LowSmsCredit($warn));
        }
    }
}
