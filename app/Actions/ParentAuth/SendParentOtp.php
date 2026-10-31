<?php

namespace App\Actions\ParentAuth;

use App\Actions\Sms\QueueSms;
use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Models\MessageCreditEntry;
use App\Models\ParentOtp;
use App\Support\Phone;
use App\Support\Sms\SmsSegments;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Texts a one-time code to a parent's phone, used both to activate the parent app and to set a
 * new password. The answer is the same whether or not the number belongs to a guardian, so the
 * app cannot be used to find out who is registered: the credit check and the 60-second wait
 * apply to every number, and a number with no app access simply gets no text.
 */
class SendParentOtp
{
    public const RESEND_SECONDS = 60;

    public function __construct(
        private CurrentSchool $currentSchool,
        private FindParentGuardian $findParentGuardian,
        private QueueSms $queueSms,
    ) {}

    public function handle(string $phone): void
    {
        $school = $this->currentSchool->require();
        $normalised = Phone::normalise($phone)
            ?? throw ValidationException::withMessages(['phone' => 'Enter a Ghana phone number, like 024 412 3456.']);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $message = "Your {$school->name} parent app code is {$code}. It expires in ".ParentOtp::TTL_MINUTES.' minutes. Do not share it.';

        if (MessageCreditEntry::balance() < SmsSegments::count($message)) {
            throw $this->noCredit();
        }

        $key = "parent-otp-resend:{$school->id}:{$normalised}";
        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['phone' => 'Please wait '.RateLimiter::availableIn($key).' seconds before asking for another code.']);
        }
        RateLimiter::hit($key, self::RESEND_SECONDS);

        if ($this->findParentGuardian->handle($normalised) === null) {
            return;
        }

        // Only the newest code works.
        ParentOtp::where('phone', $normalised)->whereNull('used_at')->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $otp = ParentOtp::create([
            'phone' => $normalised,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(ParentOtp::TTL_MINUTES),
        ]);

        $sms = $this->queueSms->handle($normalised, $message, SmsEvent::Otp);
        $otp->forceFill(['sms_message_id' => $sms->id])->save();

        // Another message took the last units between the check above and now.
        if ($sms->status === SmsStatus::NotSent) {
            $otp->forceFill(['expires_at' => now()])->save();

            throw $this->noCredit();
        }
    }

    private function noCredit(): ValidationException
    {
        return ValidationException::withMessages(['phone' => "Your school can't send codes right now. Please contact the school."]);
    }
}
