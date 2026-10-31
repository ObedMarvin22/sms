<?php

namespace App\Actions\Announcements;

use App\Actions\Attendance\AttendanceRules;
use App\Enums\AnnouncementAudience;
use App\Enums\GuardianAccess;
use App\Enums\PermissionKey;
use App\Models\Announcement;
use App\Models\Enrolment;
use App\Models\GuardianStudent;
use App\Models\Notification;
use App\Models\Stream;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who may send to whom, and which families an announcement reaches (Obed's rules, step 8).
 */
final class AnnouncementRules
{
    /**
     * The whole school or any class: announcements.send and students.manage (heads, administrators).
     */
    public static function canSendToSchool(User $user): bool
    {
        return $user->can(PermissionKey::AnnouncementsSend->value) && $user->can(PermissionKey::StudentsManage->value);
    }

    /**
     * Streams this user may send to: all for heads and administrators, otherwise the streams
     * they are class teacher of this year (the register's rule), otherwise none.
     *
     * @return Collection<int, Stream>
     */
    public static function streamsFor(User $user): Collection
    {
        if (! $user->can(PermissionKey::AnnouncementsSend->value)) {
            return new Collection;
        }

        $query = Stream::query()->select('streams.*')
            ->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->with('schoolClass')
            ->orderBy('school_classes.sequence')->orderBy('streams.name');

        return self::canSendToSchool($user)
            ? $query->get()
            : $query->whereIn('streams.id', AttendanceRules::classTeacherStreamIds($user))->get();
    }

    /**
     * How many families got each announcement's notice, and how many opened it.
     *
     * @param  list<int>  $announcementIds
     * @return array<int, array{total: int, seen: int}>
     */
    public static function seenCounts(array $announcementIds): array
    {
        if ($announcementIds === []) {
            return [];
        }

        $rows = Notification::query()
            ->where('type', AnnouncementPosted::class)
            ->whereIn('data->announcement_id', $announcementIds)
            ->selectRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.announcement_id')) AS UNSIGNED) AS announcement_id")
            ->selectRaw('COUNT(*) AS total, SUM(read_at IS NOT NULL) AS seen')
            ->groupBy('announcement_id')
            ->toBase()
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->announcement_id] = ['total' => (int) $row->total, 'seen' => (int) $row->seen];
        }

        return $counts;
    }

    /**
     * The guardians an announcement reaches: above "contact only" for a child on file who is, for
     * a stream audience, currently in one of its streams.
     *
     * @return Builder<GuardianStudent>
     */
    public static function audienceLinks(Announcement $announcement): Builder
    {
        $links = GuardianStudent::query()
            ->where('access_level', '!=', GuardianAccess::None->value)
            ->whereHas('student', fn ($students) => $students->whereNull('students.deleted_at'));

        if ($announcement->audience === AnnouncementAudience::Streams) {
            $links->whereIn('student_id', Enrolment::query()->current()
                ->whereIn('stream_id', $announcement->streams()->pluck('streams.id'))
                ->select('student_id'));
        }

        return $links;
    }
}
