<?php

namespace App\Actions\Sms;

use App\Enums\CreditEntryType;
use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Jobs\SendSms;
use App\Models\MessageCreditEntry;
use App\Models\School;
use App\Models\SmsMessage;
use App\Support\Sms\SmsSegments;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Queues one SMS for the current school. Never sends on credit (SPEC section 9): the school row
 * is locked, the balance checked and the units taken in one transaction, so two messages at the
 * same moment can never overdraw. Without enough units the message is kept as "not sent" and
 * nothing is taken. Sending happens in a queued job (cron), so callers never wait on Arkesel.
 */
class QueueSms
{
    public function __construct(private CurrentSchool $currentSchool, private CheckLowCredit $checkLowCredit) {}

    public function handle(string $to, string $message, SmsEvent $event): SmsMessage
    {
        $school = $this->currentSchool->require();
        $segments = SmsSegments::count($message);

        $sms = DB::transaction(function () use ($school, $to, $message, $event, $segments): SmsMessage {
            // The school row is the wallet's lock.
            School::whereKey($school->id)->lockForUpdate()->firstOrFail();

            $sms = new SmsMessage([
                'to' => $to,
                'message' => $message,
                'segments' => $segments,
                'sender' => SmsSettings::sender($school),
                'event' => $event->value,
            ]);

            if (MessageCreditEntry::balance() < $segments) {
                $sms->forceFill(['status' => SmsStatus::NotSent, 'error' => 'Not enough SMS credit.'])->save();

                return $sms;
            }

            $sms->save();
            MessageCreditEntry::create(['type' => CreditEntryType::Message, 'units' => -$segments, 'sms_message_id' => $sms->id]);

            return $sms;
        });

        if ($sms->status === SmsStatus::Queued) {
            SendSms::dispatch($school->id, $sms->id);
        }
        $this->checkLowCredit->handle();

        return $sms;
    }
}
