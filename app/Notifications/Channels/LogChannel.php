<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Stand-in for SMS until Sprint 4: writes the message and the recipient's phone to
 * storage/logs/notifications.log instead of sending it.
 */
class LogChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof HasLogMessage) {
            return;
        }

        $phone = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms', $notification) : null;

        Log::channel('notifications')->info($notification->toLog($notifiable), [
            'to' => is_string($phone) ? $phone : null,
            'notification' => $notification::class,
        ]);
    }
}
