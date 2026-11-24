<?php

namespace App\Actions\Assessment;

use App\Enums\PermissionKey;
use App\Enums\SheetStatus;
use App\Models\AssessmentSheet;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Reopens a submitted mark sheet so marks can be corrected. Heads only (grades.approve), always
 * with a reason; the reason is shown on the sheet and kept in the activity log.
 */
class UnlockSheet
{
    public const MIN_REASON = 10;

    public function handle(User $by, AssessmentSheet $sheet, string $reason): AssessmentSheet
    {
        if (! $by->can(PermissionKey::GradesApprove->value)) {
            throw new AuthorizationException('Only the head can unlock submitted marks.');
        }
        if (! $sheet->isLocked()) {
            throw ValidationException::withMessages(['unlock_reason' => 'These marks are not locked.']);
        }

        $reason = preg_replace('/\s+/', ' ', trim($reason)) ?? '';
        if (mb_strlen($reason) < self::MIN_REASON || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['unlock_reason' => 'Say why in a few words (10 to 500 characters), e.g. "Wrong exam mark for Kofi".']);
        }

        $sheet->update([
            'status' => SheetStatus::Reopened,
            'unlocked_by' => $by->id,
            'unlocked_at' => now(),
            'unlock_reason' => $reason,
        ]);

        return $sheet;
    }
}
