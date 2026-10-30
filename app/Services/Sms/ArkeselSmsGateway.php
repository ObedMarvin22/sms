<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Arkesel SMS API v2 (Ghana): POST /api/v2/sms/send with the "api-key" header. Really sends and
 * costs money; only used when SMS_DRIVER=arkesel. Tests use Http::fake().
 */
class ArkeselSmsGateway implements SmsGateway
{
    public function __construct(private string $baseUrl, private ?string $apiKey) {}

    public static function fromConfig(): self
    {
        return new self((string) config('sms.arkesel.base_url'), config('sms.arkesel.api_key'));
    }

    public function send(string $to, string $message, string $sender): string
    {
        if ((string) $this->apiKey === '') {
            throw new SmsException('SMS is not set up on this server (no Arkesel key).');
        }

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->withHeaders(['api-key' => (string) $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post('/api/v2/sms/send', [
                    'sender' => $sender,
                    'message' => $message,
                    // Arkesel takes international numbers without the plus.
                    'recipients' => [ltrim($to, '+')],
                ]);
        } catch (ConnectionException) {
            throw new SmsException('Could not reach Arkesel.');
        }

        $json = $response->json();
        if ($response->failed() || ! is_array($json) || ($json['status'] ?? null) !== 'success') {
            throw new SmsException(is_array($json) && is_string($json['message'] ?? null) ? $json['message'] : 'Arkesel did not accept the message.');
        }

        $first = is_array($json['data'] ?? null) ? ($json['data'][0] ?? []) : [];

        return is_array($first) && isset($first['id']) ? (string) $first['id'] : 'arkesel';
    }
}
