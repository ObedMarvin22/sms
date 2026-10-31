<?php

namespace App\Actions\Sms;

use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use App\Services\Sms\SmsGateway;

/**
 * Sends a queued SMS through the gateway. A provider error is thrown on, so the job retries;
 * after the last try FailSms gives the units back.
 */
class DeliverSms
{
    public function __construct(private SmsGateway $gateway) {}

    public function handle(int $smsId): void
    {
        $sms = SmsMessage::find($smsId);
        if ($sms === null || $sms->status !== SmsStatus::Queued) {
            return; // already sent, failed or never queued: never send twice
        }

        $providerId = $this->gateway->send($sms->to, $sms->message, $sms->sender);

        $sms->forceFill(['status' => SmsStatus::Sent, 'provider_id' => mb_substr($providerId, 0, 100), 'sent_at' => now(), 'error' => null]);
        // A sent code is no longer needed; keep the record without it.
        if ($sms->event === SmsEvent::Otp->value) {
            $sms->message = SmsMessage::hideCodes($sms->message);
        }
        $sms->save();
    }
}
