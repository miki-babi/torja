<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\ChapaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Chapa payment API (https://developer.chapa.co).
 * The official chapa-laravel package is incompatible with this app's
 * Laravel/PHP versions, so we call the REST endpoints directly.
 */
class ChapaService
{
    /**
     * Initialize a transaction. Returns the parsed response body; the
     * hosted checkout URL is at `data.checkout_url`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ChapaException when the request fails or Chapa reports an error
     */
    public function initialize(array $payload): array
    {
        try {
            $response = $this->client()
                ->retry(2, 200, throw: false)
                ->post('/transaction/initialize', $payload);
        } catch (ConnectionException $e) {
            throw new ChapaException('Chapa initialize failed: '.$e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed() || ($body['status'] ?? null) !== 'success') {
            throw new ChapaException(
                'Chapa initialize failed: '.$this->errorMessage($body, $response->body())
            );
        }

        return $body;
    }

    /**
     * Verify a transaction by its tx_ref. Never throws — returns the parsed
     * body (with `status`/`data.status`) or an error-shaped array on failure.
     *
     * @return array<string, mixed>
     */
    public function verify(string $txRef): array
    {
        try {
            $response = $this->client()->get("/transaction/verify/{$txRef}");
        } catch (ConnectionException $e) {
            return [
                'status' => 'failed',
                'message' => 'Verification request failed: '.$e->getMessage(),
                'data' => null,
            ];
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed()) {
            return [
                'status' => 'failed',
                'message' => $body['message'] ?? 'Verification request failed.',
                'data' => $body['data'] ?? null,
            ];
        }

        return $body;
    }

    /**
     * Request a refund for a transaction. Best-effort: Chapa may still
     * require the refund to be approved from their dashboard.
     *
     * @return array<string, mixed>
     */
    public function refund(string $txRef): array
    {
        try {
            $response = $this->client()->post("/refund/{$txRef}");
        } catch (ConnectionException $e) {
            throw new ChapaException('Chapa refund failed: '.$e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed()) {
            throw new ChapaException(
                'Chapa refund failed: '.$this->errorMessage($body, $response->body())
            );
        }

        return $body;
    }

    /**
     * Chapa returns `message` either as a string or as an object of
     * field => errors arrays; flatten it into one readable string.
     *
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body, string $fallback): string
    {
        $message = $body['message'] ?? null;

        if (is_array($message)) {
            $message = implode('; ', Arr::flatten($message));
        }

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * Validate the `x-chapa-signature` webhook header (HMAC-SHA256 of the
     * raw request body with the webhook secret).
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('services.chapa.webhook_secret');
        $signature = $request->header('x-chapa-signature');

        if (! is_string($secret) || $secret === '' || ! is_string($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Authenticated client for the Chapa API.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.chapa.base_url'))
            ->withToken((string) config('services.chapa.secret_key'))
            ->acceptJson()
            ->timeout(15);
    }
}
