<?php

namespace App\Jobs;

use App\Actions\Announcements\NotifyAnnouncementGuardians;
use App\Jobs\Middleware\RunInSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * In-app notices for up to 200 guardians of one announcement. Safe to retry.
 */
class SendAnnouncementNotices implements SchoolJob, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 45;

    /**
     * @param  list<int>  $guardianIds
     */
    public function __construct(public int $school, public int $announcementId, public array $guardianIds) {}

    public function schoolId(): int
    {
        return $this->school;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunInSchool];
    }

    public function handle(NotifyAnnouncementGuardians $notify): void
    {
        $notify->handle($this->announcementId, $this->guardianIds);
    }
}
