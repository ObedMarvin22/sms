<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Tells finance staff the school's SMS credit is running low (CheckLowCredit). In-app only:
 * an SMS about running out of SMS would make it worse.
 */
class LowSmsCredit extends Notification
{
    public function __construct(public readonly int $units) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'units' => $this->units,
            'message' => "Only {$this->units} SMS units left. Ask Orange Tree to add more; until then, notices go in the app only.",
        ];
    }
}
