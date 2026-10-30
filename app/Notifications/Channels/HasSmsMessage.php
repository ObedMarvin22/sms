<?php

namespace App\Notifications\Channels;

use App\Enums\SmsEvent;

/**
 * A notification that can also go by SMS (SmsChannel), when the school has turned on its event.
 */
interface HasSmsMessage
{
    public function smsEvent(): SmsEvent;

    public function toSms(object $notifiable): string;
}
