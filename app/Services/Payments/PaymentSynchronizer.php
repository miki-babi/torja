<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Re-verifies a pending payment against whichever provider created it and
 * persists the result. Shared by the return callback, the user re-verify
 * action, the Verify Checkout webhook and the admin re-verify button.
 */
class PaymentSynchronizer
{
    public function __construct(
        private readonly ChapaService $chapa,
        private readonly VerifyCheckoutService $verifyCheckout,
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

        if ($payment->isVerifyCheckout()) {
            $this->syncVerifyCheckout($payment);
        } elseif ($payment->isJami()) {
            $this->syncJami($payment);
        } else {
            $this->syncChapa($payment);
        }
    }

    /**
     * Verify the payment with Chapa and persist the result.
     */
    private function syncChapa(Payment $payment): void
    {
        $response = $this->chapa->verify($payment->tx_ref);

        $payment->update(['provider_response' => $response]);

        if (data_get($response, 'data.status') === 'success') {
            $payment->markSuccessful();
        } else {
            $payment->update(['status' => 'failed']);
        }
    }

    /**
     * Look up the Verify Checkout deposit and map its status onto the local
     * payment. `succeeded` fulfills; `review_required` is held; terminal
     * failures are recorded; anything else stays pending. A missing
     * deposit_id means the create call never returned — nothing to check.
     */
    private function syncVerifyCheckout(Payment $payment): void
    {
        if ($payment->deposit_id === null) {
            return;
        }

        $response = $this->verifyCheckout->deposit($payment->deposit_id);

        $payment->update(['provider_response' => array_merge($payment->provider_response ?? [], $response)]);

        $remoteStatus = data_get($response, 'data.status');

        if (! is_string($remoteStatus)) {
            return;
        }

        if ($remoteStatus === 'succeeded') {
            $payment->markSuccessful();
        } else {
            $payment->update(['status' => $this->verifyCheckout->localStatus($remoteStatus)]);
        }
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
