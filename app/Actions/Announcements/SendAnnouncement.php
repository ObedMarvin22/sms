<?php

namespace App\Actions\Announcements;

use App\Enums\AnnouncementAudience;
use App\Jobs\SendAnnouncementNotices;
use App\Models\Announcement;
use App\Models\AnnouncementStream;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Sends an announcement now: stores it, then queues in-app notices to the families in chunks so
 * the screen never waits on hundreds of rows. In-app only (Obed's choice, step 8).
 */
class SendAnnouncement
{
    public const CHUNK = 200;

    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @param  array{title?: mixed, body?: mixed, audience?: mixed, stream_ids?: mixed}  $input
     */
    public function handle(User $user, array $input): Announcement
    {
        $allowed = AnnouncementRules::streamsFor($user)->modelKeys();
        if ($allowed === []) {
            throw new AuthorizationException('You cannot send announcements.');
        }

        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::enum(AnnouncementAudience::class)],
            'stream_ids' => ['exclude_unless:audience,streams', 'required', 'array', 'min:1'],
            'stream_ids.*' => ['integer', Rule::in($allowed)],
        ], [
            'stream_ids.required' => 'Choose at least one class.',
            'stream_ids.*.in' => 'You can only send to your own classes.',
        ])->validate();

        $audience = AnnouncementAudience::from($data['audience']);
        if ($audience === AnnouncementAudience::School && ! AnnouncementRules::canSendToSchool($user)) {
            throw new AuthorizationException('Only heads and administrators can send to the whole school.');
        }

        $announcement = DB::transaction(function () use ($user, $data, $audience): Announcement {
            $announcement = new Announcement(['title' => trim($data['title']), 'body' => trim($data['body']), 'audience' => $audience]);
            $announcement->forceFill(['sent_by' => $user->id, 'sent_at' => now()])->save();

            foreach (array_unique($data['stream_ids'] ?? []) as $streamId) {
                AnnouncementStream::create(['announcement_id' => $announcement->id, 'stream_id' => (int) $streamId]);
            }

            return $announcement;
        });

        $schoolId = $this->currentSchool->require()->id;
        AnnouncementRules::audienceLinks($announcement)->distinct()->orderBy('guardian_id')->pluck('guardian_id')
            ->chunk(self::CHUNK)
            ->each(fn ($ids) => SendAnnouncementNotices::dispatch($schoolId, $announcement->id, array_values($ids->map(intval(...))->all())));

        return $announcement;
    }
}
