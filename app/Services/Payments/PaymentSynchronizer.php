<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Re-verifies a pending payment against Jami and persists the result.
 * Shared by the return callback, the user re-verify action, and the Jami
 * webhook.
 */
class PaymentSynchronizer
{
    public function __construct(
        private readonly JamiService $jami,
    ) {}

    /**
     * Fetch the authoritative remote status and persist it. Idempotent:
     * already-successful payments are left untouched.
     */
    public function sync(Payment $payment): void
    {
        if ($payment->isSuccessful()) {
            return;
        }

        $this->syncJami($payment);
    }

    /**
     * Look up the Jami checkout session and map it onto the local payment.
     * `completed` with a `paid` order fulfills; `expired` is a terminal
     * failure; `open` stays pending. A missing session id means the create
     * call never returned — nothing to check.
     */
    private function syncJami(Payment $payment): void
    {
        if ($payment->deposit_id === null) {
            return;
        }

        $response = $this->jami->checkoutStatus($payment->deposit_id);

        // Merge rather than replace — the stored response also carries the
        // checkout `checkoutUrl` and direct-push details for the overlay.
        $payment->update(['provider_response' => array_merge($payment->provider_response ?? [], $response)]);

        $status = data_get($response, 'status');

        if (! is_string($status)) {
            return;
        }

        if ($status === 'completed' && data_get($response, 'orderStatus') === 'paid') {
            $payment->markSuccessful();
        } elseif ($status === 'expired') {
            $payment->update(['status' => 'failed']);
        }
    }
}
