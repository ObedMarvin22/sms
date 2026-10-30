<?php

namespace App\Services\Paystack;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Paystack REST API through Laravel's HTTP client (no SDK; SPEC section 2). Amounts are in
 * pesewas, as Paystack expects for GHS. Tests use Http::fake(); never call it for real in tests.
 */
class PaystackClient
{
    public function __construct(
        private string $baseUrl,
        private ?string $secretKey,
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('services.paystack.base_url'), config('services.paystack.secret_key'));
    }

    public function configured(): bool
    {
        return (string) $this->secretKey !== '';
    }

    /**
     * Banks and mobile money providers that can receive settlements in Ghana (cached a day).
     *
     * @return list<array{name: string, code: string, type: string}>
     */
    public function banks(): array
    {
        /** @var list<array{name: string, code: string, type: string}> */
        return Cache::remember('paystack.banks.ghana', now()->addDay(), function (): array {
            $data = $this->get('/bank', ['country' => 'ghana', 'currency' => 'GHS', 'perPage' => 200]);

            return array_values(array_map(fn (array $bank) => [
                'name' => (string) $bank['name'],
                'code' => (string) $bank['code'],
                'type' => (string) ($bank['type'] ?? ''),
            ], $data));
        });
    }

    /**
     * The account holder's name, as the bank has it.
     */
    public function resolveAccount(string $accountNumber, string $bankCode): string
    {
        $data = $this->get('/bank/resolve', ['account_number' => $accountNumber, 'bank_code' => $bankCode]);

        return (string) ($data['account_name'] ?? '');
    }

    /**
     * A subaccount the school's money settles to. Orange Tree's share is 0%: we never hold
     * school money (SPEC section 8).
     */
    public function createSubaccount(string $businessName, string $bankCode, string $accountNumber, ?string $email): string
    {
        $data = $this->post('/subaccount', array_filter([
            'business_name' => $businessName,
            'settlement_bank' => $bankCode,
            'account_number' => $accountNumber,
            'percentage_charge' => 0,
            'primary_contact_email' => $email,
        ], fn ($value) => $value !== null));

        return (string) ($data['subaccount_code'] ?? throw new PaystackException('Paystack did not return a subaccount code.'));
    }

    /**
     * Starts a payment; the parent is sent to authorization_url.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: string, reference: string}
     */
    public function initialize(string $email, int $amount, string $reference, string $subaccount, string $callbackUrl, array $metadata): array
    {
        $data = $this->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amount,
            'currency' => 'GHS',
            'reference' => $reference,
            'subaccount' => $subaccount,
            // The school's subaccount bears Paystack's charge; when parents pay it, it is
            // already added to the amount.
            'bearer' => 'subaccount',
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        return [
            'authorization_url' => (string) ($data['authorization_url'] ?? throw new PaystackException('Paystack did not return a payment page.')),
            'reference' => (string) ($data['reference'] ?? $reference),
        ];
    }

    /**
     * What Paystack says about a transaction. Always ask before trusting a webhook.
     *
     * @return array<string, mixed>
     */
    public function verify(string $reference): array
    {
        return $this->get('/transaction/verify/'.rawurlencode($reference));
    }

    /**
     * Whether a webhook body really came from Paystack: x-paystack-signature is the
     * HMAC-SHA512 of the raw body with our secret key.
     */
    public function signatureIsValid(string $rawBody, ?string $signature): bool
    {
        if (! $this->configured() || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, (string) $this->secretKey), $signature);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get($path, $query));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->send(fn (PendingRequest $request) => $request->post($path, $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<mixed>
     */
    private function send(callable $call): array
    {
        if (! $this->configured()) {
            throw new PaystackException('Online payments are not set up on this server (no Paystack key).');
        }

        try {
            $response = $call(Http::baseUrl($this->baseUrl)
                ->withToken((string) $this->secretKey)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, 500, fn ($exception) => $exception instanceof ConnectionException, throw: false));
        } catch (ConnectionException) {
            throw new PaystackException('Could not reach Paystack. Try again in a minute.');
        }

        $json = $response->json();
        if ($response->failed() || ! is_array($json) || ($json['status'] ?? false) !== true) {
            $message = is_array($json) && is_string($json['message'] ?? null) ? $json['message'] : 'Paystack could not complete the request.';

            throw new PaystackException($message);
        }

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }
}
