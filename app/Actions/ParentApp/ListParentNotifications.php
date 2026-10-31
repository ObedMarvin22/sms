<?php

namespace App\Actions\ParentApp;

use App\Enums\GuardianAccess;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Notification;
use App\Notifications\PaymentReceived;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The guardian's in-app notices, newest first. Notices about a child they can no longer see are
 * left out, and payment notices (which show the balance) need full access to that child.
 */
class ListParentNotifications
{
    /**
     * @return LengthAwarePaginator<int, Notification>
     */
    public function handle(Guardian $guardian, int $perPage = 20): LengthAwarePaginator
    {
        return self::visible($guardian)->latest()->paginate($perPage);
    }

    /**
     * @return Builder<Notification>
     */
    public static function visible(Guardian $guardian): Builder
    {
        $links = FindParentChild::visibleLinks($guardian)->get(['student_id', 'access_level']);
        $all = $links->pluck('student_id')->all();
        $full = $links->filter(fn (GuardianStudent $link) => $link->access_level === GuardianAccess::Full)->pluck('student_id')->all();

        return Notification::query()
            ->where('notifiable_type', $guardian->getMorphClass())
            ->where('notifiable_id', $guardian->id)
            ->where(fn (Builder $query) => $query->whereNull('data->student_id')->orWhereIn('data->student_id', $all ?: [0]))
            ->where(fn (Builder $query) => $query->where('type', '!=', PaymentReceived::class)->orWhereIn('data->student_id', $full ?: [0]));
    }
}
