<?php

namespace App\Notifications;

use App\Enums\SmsEvent;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Notifications\Channels\HasSmsMessage;
use App\Notifications\Channels\SmsChannel;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;

/**
 * Tells a guardian their child was marked absent on today's register (sent by NotifyAbsences).
 */
class StudentAbsent extends Notification implements HasSmsMessage
{
    public function __construct(
        public readonly AttendanceRecord $record,
        public readonly CarbonImmutable $date,
        public readonly School $school,
    ) {}

    /**
     * In-app always; SMS when the school turned absence SMS on (SmsChannel decides).
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class];
    }

    public function smsEvent(): SmsEvent
    {
        return SmsEvent::Absence;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->record->student_id,
            'student_name' => $this->record->student->full_name,
            'date' => $this->date->toDateString(),
            'message' => $this->message(),
        ];
    }

    public function toSms(object $notifiable): string
    {
        return $this->message();
    }

    private function message(): string
    {
        return "{$this->record->student->first_name} was marked absent at {$this->school->name} today ({$this->date->format('D j M')}). "
            .'If you did not expect this, please contact the school.';
    }
}
