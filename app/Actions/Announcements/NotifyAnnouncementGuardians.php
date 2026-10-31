<?php

namespace App\Actions\Announcements;

use App\Models\Announcement;
use App\Models\Guardian;
use App\Models\Notification;
use App\Notifications\AnnouncementPosted;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Gives some guardians the in-app notice for an announcement. Safe to run twice: a guardian who
 * already has it is skipped, and nothing goes out once the announcement is withdrawn.
 */
class NotifyAnnouncementGuardians
{
    /**
     * @param  list<int>  $guardianIds
     */
    public function handle(int $announcementId, array $guardianIds): int
    {
        $announcement = Announcement::live()->find($announcementId);
        if ($announcement === null) {
            return 0;
        }

        $already = Notification::where('type', AnnouncementPosted::class)
            ->where('notifiable_type', (new Guardian)->getMorphClass())
            ->whereIn('notifiable_id', $guardianIds)
            ->where('data->announcement_id', $announcementId)
            ->pluck('notifiable_id')->all();

        $guardians = Guardian::whereIn('id', array_diff($guardianIds, $already))->get();
        Notifier::send($guardians, new AnnouncementPosted($announcement));

        return $guardians->count();
    }
}
