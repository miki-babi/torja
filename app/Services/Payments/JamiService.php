<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\JamiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Jami merchant API (https://jami.bio/api/jamidev).
 * A checkout session is either a `redirect` payment link or a `direct`
 * push to the buyer's phone; the session id is the reconciliation key —
 * webhooks carry it as `data.sessionId` and the status endpoint polls by it.
 */
class JamiService
{
    /**
     * Create a checkout session for a custom amount. `redirect` sessions
     * carry a `checkoutUrl` payment link; `direct` sessions have already
     * pushed the prompt to the buyer's phone.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws JamiException when the request fails or the API reports an error
     */
    public function createCheckout(array $payload): array
    {
        try {
            $response = $this->client()->post('/api/jamidev/checkout', $payload);
        } catch (ConnectionException $e) {
            throw new JamiException('Jami checkout creation failed: '.$e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed() || ! is_string($body['sessionId'] ?? null)) {
            throw new JamiException(
                'Jami checkout creation failed: '.$this->errorMessage($body, $response->body())
            );
        }

        return $body;
    }

    /**
     * Retrieve the authoritative state of a checkout session. The API
     * reconciles with the payment provider on each call, so this resolves
     * even when a webhook is late. Never throws — returns the parsed body
     * or an error-shaped array on failure.
     *
     * @return array<string, mixed>
     */
    public function checkoutStatus(string $sessionId): array
    {
        try {
            $response = Http::baseUrl((string) config('services.jami.base_url'))
                ->acceptJson()
                ->timeout(15)
                ->get("/api/jamidev/checkout/{$sessionId}");
        } catch (ConnectionException $e) {
            return [
                'status' => null,
                'error' => ['code' => 'request_failed', 'message' => 'Checkout lookup failed: '.$e->getMessage()],
            ];
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed()) {
            return [
                'status' => null,
                'error' => $body['error'] ?? ['code' => 'request_failed', 'message' => 'Checkout lookup failed.'],
            ];
        }

        return $body;
    }

    /**
     * Validate the `X-JamiDev-Signature` header: `t=<unix>,v1=<64 hex>` where
     * `v1` is the HMAC-SHA256 of `"<t>.<exact raw body>"` with the endpoint
     * secret. Rejects timestamps outside a five-minute tolerance and
     * malformed signatures.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('services.jami.webhook_secret');
        $signature = $request->header('X-JamiDev-Signature');

        if (! is_string($secret) || $secret === ''
            || ! is_string($signature)
            || ! preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $signature, $matches)) {
            return false;
        }

        [, $timestamp, $v1] = $matches;

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $v1);
    }

    /**
     * Pull a human-readable reason out of the API's error shapes
     * (`error` string/object or top-level `message`); fall back to the
     * raw body when the shape is unexpected.
     *
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body, string $fallback): string
    {
        $message = $body['error']['message'] ?? $body['error'] ?? $body['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * Authenticated client for the Jami API.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.jami.base_url'))
            ->withToken((string) config('services.jami.token'))
            ->acceptJson()
            ->timeout(15);
    }
}
