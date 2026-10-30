<?php

namespace App\Actions\Paystack;

use App\Jobs\ProcessPaystackEvent;
use App\Models\PaystackEvent;
use App\Services\Paystack\PaystackClient;

/**
 * Receives a webhook: checks Paystack's signature, stores the event once (its key is unique, so
 * a repeated delivery does nothing) and queues it. Paystack gets its answer straight away; the
 * job verifies and records within a minute (cron runs the queue). Platform code: no school yet.
 */
class HandlePaystackWebhook
{
    public function __construct(private PaystackClient $paystack) {}

    /**
     * @return bool false when the signature is wrong (the caller answers 401)
     */
    public function handle(string $rawBody, ?string $signature): bool
    {
        if (! $this->paystack->signatureIsValid($rawBody, $signature)) {
            return false;
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || ! is_string($payload['event'] ?? null)) {
            return true; // signed but meaningless: acknowledge so Paystack stops retrying
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $key = mb_substr($payload['event'].':'.($data['id'] ?? $data['reference'] ?? sha1($rawBody)), 0, 120);

        $event = PaystackEvent::firstOrCreate(['event_key' => $key], [
            'event' => mb_substr($payload['event'], 0, 60),
            'reference' => is_string($data['reference'] ?? null) ? mb_substr($data['reference'], 0, 60) : null,
            'payload' => $payload,
        ]);

        if ($event->wasRecentlyCreated) {
            ProcessPaystackEvent::dispatch($event->id);
        }

        return true;
    }
}
