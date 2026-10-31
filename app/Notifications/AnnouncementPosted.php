<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;

/**
 * The parent app's notice for a new announcement (in-app only).
 */
class AnnouncementPosted extends Notification
{
    public function __construct(public Announcement $announcement) {}

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
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'message' => 'New announcement: '.$this->announcement->title,
        ];
    }
}
