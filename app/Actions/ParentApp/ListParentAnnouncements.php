<?php

namespace App\Actions\ParentApp;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Enrolment;
use App\Models\Guardian;
use App\Models\Notification;
use App\Notifications\AnnouncementPosted;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The announcements a guardian may read: not withdrawn, and for the whole school or for a stream
 * one of their children (above "contact only") is in now. Each comes with the names of the
 * children it concerns.
 */
class ListParentAnnouncements
{
    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function handle(Guardian $guardian, int $perPage = 20): LengthAwarePaginator
    {
        [$query, $children] = $this->visible($guardian);
        $page = $query->with('streams')->latest('sent_at')->latest('id')->paginate($perPage);

        $page->getCollection()->each(fn (Announcement $announcement) => $this->concerning($announcement, $children));

        return $page;
    }

    /**
     * One announcement; opening it marks its notice read. Not found if the guardian may not see it.
     */
    public function find(Guardian $guardian, int $id): Announcement
    {
        [$query, $children] = $this->visible($guardian);
        $announcement = $query->with('streams')->whereKey($id)->first()
            ?? throw (new ModelNotFoundException)->setModel(Announcement::class, [$id]);

        Notification::where('type', AnnouncementPosted::class)
            ->where('notifiable_type', $guardian->getMorphClass())
            ->where('notifiable_id', $guardian->id)
            ->where('data->announcement_id', $announcement->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->concerning($announcement, $children);
    }

    /**
     * @return array{Builder<Announcement>, array<int, list<string>>} the query, and child first names by stream id
     */
    private function visible(Guardian $guardian): array
    {
        $studentIds = FindParentChild::visibleLinks($guardian)->pluck('student_id');

        $children = [];
        foreach (Enrolment::query()->current()->with('student')->whereIn('student_id', $studentIds)->get() as $enrolment) {
            $children[$enrolment->stream_id][] = $enrolment->student->first_name;
        }
        $streamIds = array_keys($children);

        $query = Announcement::live()->where(fn (Builder $query) => $query
            ->where('audience', AnnouncementAudience::School->value)
            ->orWhereHas('streams', fn (Builder $streams) => $streams->whereIn('streams.id', $streamIds ?: [0])));

        // A family with no child on file sees nothing, not even school-wide notices.
        if ($studentIds->isEmpty()) {
            $query->whereRaw('1 = 0');
        }

        return [$query, $children];
    }

    /**
     * @param  array<int, list<string>>  $children
     */
    private function concerning(Announcement $announcement, array $children): Announcement
    {
        $names = $announcement->audience === AnnouncementAudience::School
            ? []
            : array_values(array_unique(array_merge(...array_map(fn (int $id) => $children[$id] ?? [], $announcement->streams->modelKeys()))));

        return $announcement->setAttribute('concerns', $names);
    }
}
