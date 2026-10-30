<?php

namespace App\Actions\Sms;

use App\Enums\CreditEntryType;
use App\Enums\SmsStatus;
use App\Models\MessageCreditEntry;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\DB;

/**
 * Gives up on an SMS after the last try: marks it failed and returns its units, once.
 */
class FailSms
{
    public function handle(int $smsId, string $error): void
    {
        DB::transaction(function () use ($smsId, $error): void {
            $sms = SmsMessage::whereKey($smsId)->lockForUpdate()->first();
            if ($sms === null || $sms->status !== SmsStatus::Queued) {
                return;
            }

            $sms->forceFill(['status' => SmsStatus::Failed, 'error' => mb_substr($error, 0, 255)])->save();
            MessageCreditEntry::create(['type' => CreditEntryType::Refund, 'units' => $sms->segments, 'sms_message_id' => $sms->id]);
        });
    }
}
