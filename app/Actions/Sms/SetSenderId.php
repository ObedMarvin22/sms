<?php

namespace App\Actions\Sms;

use App\Models\School;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Console: the name a school's SMS come from ("GREENHILL"), once Arkesel has approved it.
 * 3–11 letters, digits or spaces, with at least one letter. Empty clears it (platform default).
 */
class SetSenderId
{
    public function handle(User $actor, School $school, string $senderId): School
    {
        $senderId = trim($senderId);

        if ($senderId !== '' && (preg_match('/^[A-Za-z0-9 ]{3,11}$/', $senderId) !== 1 || preg_match('/[A-Za-z]/', $senderId) !== 1)) {
            throw ValidationException::withMessages(['sender_id' => 'Use 3 to 11 letters, digits or spaces, with at least one letter.']);
        }

        $school->forceFill(['sms_sender_id' => $senderId === '' ? null : $senderId])->save();

        activity('schools')->performedOn($school)->causedBy($actor)->event('sms_sender_set')
            ->withProperties(['sender_id' => $school->sms_sender_id])->log('SMS sender ID set');

        return $school;
    }
}
