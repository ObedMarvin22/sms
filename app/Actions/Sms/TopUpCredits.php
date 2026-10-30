<?php

namespace App\Actions\Sms;

use App\Enums\CreditEntryType;
use App\Models\MessageCreditEntry;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;

/**
 * Console: adds SMS units a school has bought (the price is agreed outside the app; the note
 * says how it was paid). Logged.
 */
class TopUpCredits
{
    public function __construct(private CurrentSchool $currentSchool, private CheckLowCredit $checkLowCredit) {}

    public function handle(User $actor, School $school, string $units, string $note): MessageCreditEntry
    {
        if (preg_match('/^\d{1,7}$/', trim($units)) !== 1 || (int) $units < 1) {
            throw ValidationException::withMessages(['units' => 'Enter the number of SMS units bought, like 1000.']);
        }
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 150) {
            throw ValidationException::withMessages(['note' => 'Say how it was paid, e.g. "MoMo 0244 123456, ref 98765".']);
        }

        return $this->currentSchool->runFor($school, function () use ($actor, $units, $note): MessageCreditEntry {
            $entry = MessageCreditEntry::create([
                'type' => CreditEntryType::TopUp,
                'units' => (int) $units,
                'note' => $note,
                // created_by is a users row; console staff are users with no school.
                'created_by' => $actor->id,
            ]);

            activity('schools')->performedOn($entry)->causedBy($actor)->event('sms_credit_added')
                ->withProperties(['units' => (int) $units, 'note' => $note])->log("{$units} SMS units added");

            $this->checkLowCredit->handle();

            return $entry;
        });
    }
}
