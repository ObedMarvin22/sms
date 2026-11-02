<?php

namespace App\Actions\Saas;

use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a school with an overdue invoice open (not read-only) until a date, with a reason, e.g.
 * after agreeing a payment plan. Super admins only (manage-billing, checked by the console).
 * Logged on the school. Clearing it ($until null) lets the lock apply again at once if the
 * invoice is still unpaid past the grace period.
 */
class KeepSchoolOpen
{
    public const MAX_DAYS = 90;

    public function handle(School $school, ?string $until, string $reason = ''): School
    {
        if ($until === null || trim($until) === '') {
            $school->forceFill(['saas_lock_override_until' => null, 'saas_lock_override_reason' => null])->save();

            return $school;
        }

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $until) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $until) : null;
        if (! $date instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['until' => 'Choose a date.']);
        }
        if ($date->isBefore(today()) || $date->isAfter(today()->addDays(self::MAX_DAYS))) {
            throw ValidationException::withMessages(['until' => 'Choose a date from today up to '.self::MAX_DAYS.' days ahead.']);
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['keep_reason' => 'Say why the school is kept open (e.g. the payment plan agreed).']);
        }

        $school->forceFill(['saas_lock_override_until' => $date->toDateString(), 'saas_lock_override_reason' => $reason])->save();

        return $school;
    }
}
