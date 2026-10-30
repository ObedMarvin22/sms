<?php

namespace App\Jobs;

use App\Actions\Sms\DeliverSms;
use App\Actions\Sms\FailSms;
use App\Jobs\Middleware\RunInSchool;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one queued SMS. Retries twice (1 and 5 minutes later); after that the units go back.
 */
class SendSms implements SchoolJob, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds before each retry */
    public array $backoff = [60, 300];

    public int $timeout = 30;

    public function __construct(public int $school, public int $smsId) {}

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

    public function handle(DeliverSms $deliver): void
    {
        $deliver->handle($this->smsId);
    }

    /**
     * After the last try. Runs outside the job middleware, so the school is set here.
     */
    public function failed(Throwable $exception): void
    {
        app(CurrentSchool::class)->runFor(School::findOrFail($this->school),
            fn () => app(FailSms::class)->handle($this->smsId, $exception->getMessage()));
    }
}
