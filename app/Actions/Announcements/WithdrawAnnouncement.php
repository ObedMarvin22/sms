<?php

namespace App\Actions\Announcements;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Hides a sent announcement from parents (it is kept, and the withdrawal is logged) and removes
 * its notices from their app. The sender may withdraw their own; heads and administrators any.
 */
class WithdrawAnnouncement
{
    public function handle(User $user, Announcement $announcement): Announcement
    {
        if (! AnnouncementRules::canSendToSchool($user) && $announcement->sent_by !== $user->id) {
            throw new AuthorizationException('You can only withdraw announcements you sent.');
        }
        if ($announcement->withdrawn_at !== null) {
            return $announcement;
        }

        DB::transaction(function () use ($user, $announcement): void {
            $announcement->forceFill(['withdrawn_at' => now(), 'withdrawn_by' => $user->id])->save();

            Notification::where('type', AnnouncementPosted::class)
                ->where('data->announcement_id', $announcement->id)
                ->delete();
        });

        return $announcement;
    }
}
