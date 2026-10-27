<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\VerifyCheckoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the Verify Checkout merchant API
 * (https://checkoutapi.verify.et). Deposits are created server-side with a
 * persisted idempotency key; the hosted checkout URL lives at
 * `data.checkout_url`.
 */
class VerifyCheckoutService
{
    /**
     * Create a hosted deposit for the given payment attempt. Accepts `201`
     * for a fresh deposit and `200` for an idempotent replay of the same
     * request. Returns the parsed `data` object.
     *
     * @return array<string, mixed>
     *
     * @throws VerifyCheckoutException when the request fails or the API reports an error
     */
    public function createDeposit(string $idempotencyKey, string $merchantCustomerId, string $amount, string $returnUrl): array
    {
        try {
            $response = $this->client()
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->post('/v1/deposits', [
                    'merchant_customer_id' => $merchantCustomerId,
                    'amount' => $amount,
                    'currency' => 'ETB',
                    'return_url' => $returnUrl,
                ]);
        } catch (ConnectionException $e) {
            throw new VerifyCheckoutException('Verify Checkout deposit creation failed: '.$e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed() || ! is_array($body['data'] ?? null)) {
            throw new VerifyCheckoutException(
                'Verify Checkout deposit creation failed: '.$this->errorMessage($body, $response->body())
            );
        }

        /** @var array<string, mixed> */
        return $body['data'];
    }

    /**
     * Retrieve the authoritative state of a deposit. Never throws — returns
     * the parsed body (with `data.status`) or an error-shaped array on
     * failure.
     *
     * @return array<string, mixed>
     */
    public function deposit(string $depositId): array
    {
        try {
            $response = $this->client()->get("/v1/deposits/{$depositId}");
        } catch (ConnectionException $e) {
            return [
                'data' => null,
                'error' => ['code' => 'request_failed', 'message' => 'Deposit lookup failed: '.$e->getMessage()],
            ];
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed()) {
            return [
                'data' => null,
                'error' => $body['error'] ?? ['code' => 'request_failed', 'message' => 'Deposit lookup failed.'],
            ];
        }

        return $body;
    }

    /**
     * Map a remote deposit status onto a local payment status. Only
     * `succeeded` is fulfillable; `review_required` is held, never credited.
     */
    public function localStatus(string $remoteStatus): string
    {
        return match ($remoteStatus) {
            'succeeded' => 'success',
            'failed', 'expired', 'cancelled', 'review_required' => $remoteStatus,
            default => 'pending',
        };
    }

    /**
     * Validate the `VerifyCheckout-Signature` header: `v1=<64 hex>` HMAC-SHA256
     * of `<timestamp>.<exact raw body>` with the endpoint secret. Rejects
     * timestamps outside a five-minute tolerance and malformed signatures.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('services.verify_checkout.webhook_secret');
        $signature = $request->header('VerifyCheckout-Signature');
        $timestamp = $request->header('VerifyCheckout-Timestamp');

        if (! is_string($secret) || $secret === ''
            || ! is_string($signature) || ! preg_match('/^v1=[a-f0-9]{64}$/', $signature)
            || ! is_string($timestamp) || ! preg_match('/^\d{10,13}$/', $timestamp)) {
            return false;
        }

        $timestampMs = strlen($timestamp) >= 13 ? (int) $timestamp : (int) $timestamp * 1000;
        $nowMs = (int) round(microtime(true) * 1000);

        if (abs($nowMs - $timestampMs) > 300_000) {
            return false;
        }

        $expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * The API's error envelope is `error.code` / `error.message`; fall back
     * to the raw body when the shape is unexpected.
     *
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body, string $fallback): string
    {
        $message = $body['error']['message'] ?? null;
        $code = $body['error']['code'] ?? null;

        if (is_string($message) && $message !== '') {
            return is_string($code) ? "[{$code}] {$message}" : $message;
        }

        return $fallback;
    }

    /**
     * Authenticated client for the Verify Checkout API. The contract version
     * is pinned on every request.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.verify_checkout.base_url'))
            ->withToken((string) config('services.verify_checkout.api_key'))
            ->withHeader('VerifyCheckout-Version', (string) config('services.verify_checkout.version'))
            ->acceptJson()
            ->timeout(15);
    }
}
