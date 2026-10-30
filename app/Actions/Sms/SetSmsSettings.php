<?php

namespace App\Actions\Sms;

use App\Enums\SmsEvent;
use App\Models\School;
use Illuminate\Validation\ValidationException;

/**
 * Which notices go by SMS (each off until chosen) and when to warn about low credit. Logged.
 */
class SetSmsSettings
{
    /**
     * @param  list<string>  $events  SmsEvent values
     */
    public function handle(School $school, array $events, string $threshold): School
    {
        $chosen = [];
        foreach (array_unique($events) as $value) {
            $chosen[] = (SmsEvent::tryFrom($value) ?? throw ValidationException::withMessages(['sms_events' => 'Choose from the listed notices.']))->value;
        }

        if (preg_match('/^\d{1,6}$/', trim($threshold)) !== 1) {
            throw ValidationException::withMessages(['sms_low_credit_threshold' => 'Enter a whole number of SMS units, like 100.']);
        }

        $school->settings = [...($school->settings ?? []), 'sms_events' => $chosen, 'sms_low_credit_threshold' => (int) trim($threshold)];
        $school->save();

        activity('schools')->performedOn($school)->event('sms_settings_set')
            ->withProperties(['sms_events' => $chosen, 'threshold' => (int) trim($threshold)])->log('SMS settings changed');

        return $school;
    }
}
