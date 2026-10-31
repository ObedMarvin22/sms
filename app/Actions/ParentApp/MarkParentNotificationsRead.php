<?php

namespace App\Actions\ParentApp;

use App\Models\Guardian;

/**
 * Marks one of the guardian's notices as read (404 if it is not theirs), or all of them.
 */
class MarkParentNotificationsRead
{
    public function handle(Guardian $guardian, ?string $id = null): int
    {
        $query = ListParentNotifications::visible($guardian)->whereNull('read_at');

        if ($id !== null) {
            ListParentNotifications::visible($guardian)->whereKey($id)->firstOrFail();
            $query->whereKey($id);
        }

        return $query->update(['read_at' => now()]);
    }
}
