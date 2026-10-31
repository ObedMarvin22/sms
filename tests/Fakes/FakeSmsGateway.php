<?php

namespace Tests\Fakes;

use App\Services\Sms\SmsGateway;

/**
 * Keeps sent texts in memory, so a test can read a parent's code.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string, sender: string}> */
    public array $sent = [];

    public function send(string $to, string $message, string $sender): string
    {
        $this->sent[] = ['to' => $to, 'message' => $message, 'sender' => $sender];

        return 'fake-'.count($this->sent);
    }

    /**
     * The six-digit code in the last text to this number.
     */
    public function lastCodeTo(string $to): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['to'] === $to && preg_match('/\b(\d{6})\b/', $sms['message'], $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }
}
