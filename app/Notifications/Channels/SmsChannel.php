<?php

namespace App\Notifications\Channels;

use App\Actions\Sms\QueueSms;
use App\Actions\Sms\SmsSettings;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Notifications\Notification;

/**
 * Sends a notification by SMS when the school has turned that notice on (School profile → SMS).
 * QueueSms checks credit and queues it; the in-app copy goes regardless.
 */
class SmsChannel
{
    public function __construct(private CurrentSchool $currentSchool, private QueueSms $queueSms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof HasSmsMessage || ! method_exists($notifiable, 'routeNotificationFor')) {
            return;
        }

        $school = $this->currentSchool->require();
        if (! SmsSettings::enabled($school, $notification->smsEvent())) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('sms', $notification);
        if (! is_string($phone) || $phone === '') {
            return;
        }

        $this->queueSms->handle($phone, $notification->toSms($notifiable), $notification->smsEvent());
    }
}
