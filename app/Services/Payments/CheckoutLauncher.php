<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\ChapaException;
use App\Exceptions\Payments\JamiException;
use App\Exceptions\Payments\VerifyCheckoutException;
use App\Models\Payment;
use Illuminate\Support\Facades\Crypt;

/**
 * Result of launching a checkout: `checkoutUrl` means send the buyer to the
 * hosted page (Inertia::location); `pushInitiated` means a direct push is
 * waiting for phone approval and the caller should stay on-page.
 */
final class CheckoutLaunch
{
    private function __construct(
        public readonly ?string $checkoutUrl,
        public readonly bool $pushInitiated,
    ) {}

    public static function redirect(string $url): self
    {
        return new self($url, false);
    }

    public static function push(): self
    {
        return new self(null, true);
    }

    public static function failed(): self
    {
        return new self(null, false);
    }
}

/**
 * Starts the provider checkout for a payment — shared by the Pro unlock
 * checkout and the per-program purchase flow. Keeps provider details
 * (hosted URL, deposit id, direct-push metadata) on the payment row.
 */
class CheckoutLauncher
{
    public function __construct(
        private readonly ChapaService $chapa,
        private readonly VerifyCheckoutService $verifyCheckout,
        private readonly JamiService $jami,
    ) {}

    /**
     * Launch the checkout and report how the caller should route the buyer.
     */
    public function start(Payment $payment, ?string $gateway = null, ?string $phone = null): CheckoutLaunch
    {
        if ($payment->isJami()) {
            return $this->jami($payment, $gateway, $phone);
        }

        $checkoutUrl = $payment->isVerifyCheckout()
            ? $this->verifyCheckoutUrl($payment)
            : $this->chapaUrl($payment);

        return $checkoutUrl !== null
            ? CheckoutLaunch::redirect($checkoutUrl)
            : CheckoutLaunch::failed();
    }

    /**
     * Initialize a Chapa checkout and return the hosted checkout URL, or
     * null when Chapa rejects the request.
     */
    private function chapaUrl(Payment $payment): ?string
    {
        $returnUrl = $this->returnUrl($payment);

        try {
            $response = $this->chapa->initialize([
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'email' => $payment->email,
                'first_name' => $payment->first_name ?? 'Customer',
                'last_name' => $payment->last_name ?? '-',
                'tx_ref' => $payment->tx_ref,
                'callback_url' => $returnUrl,
                'return_url' => $returnUrl,
                'customization' => [
                    'title' => 'Toraj Pro',
                    'description' => 'One-time payment for lifetime Pro access.',
                ],
            ]);
        } catch (ChapaException $e) {
            report($e);

            return null;
        }

        $payment->update(['provider_response' => $response]);

        $checkoutUrl = $response['data']['checkout_url'] ?? null;

        return is_string($checkoutUrl) ? $checkoutUrl : null;
    }

    /**
     * Create a Verify Checkout deposit and return the hosted checkout URL,
     * or null when the API rejects the request. The deposit id and the
     * idempotency key are persisted so retries and webhooks reconcile.
     */
    private function verifyCheckoutUrl(Payment $payment): ?string
    {
        try {
            $data = $this->verifyCheckout->createDeposit(
                (string) $payment->idempotency_key,
                "user-{$payment->user_id}",
                (string) $payment->amount,
                $this->returnUrl($payment),
            );
        } catch (VerifyCheckoutException $e) {
            report($e);

            return null;
        }

        $payment->update([
            'deposit_id' => $data['id'] ?? null,
            'provider_response' => $data,
        ]);

        $checkoutUrl = $data['checkout_url'] ?? null;

        return is_string($checkoutUrl) ? $checkoutUrl : null;
    }

    /**
     * Create a Jami checkout session: a payment link yields a hosted
     * redirect, while a direct push prompts the buyer's phone. The session
     * id and push metadata are persisted for reconciliation.
     */
    private function jami(Payment $payment, ?string $gateway, ?string $phone): CheckoutLaunch
    {
        try {
            $data = $this->jami->createCheckout(array_filter([
                'amount' => (int) round((float) $payment->amount * 100),
                'description' => 'Toraj Pro — lifetime access',
                'customer' => array_filter([
                    'email' => $payment->email,
                    'phone' => $phone,
                    'name' => trim("{$payment->first_name} {$payment->last_name}"),
                    'externalId' => "user-{$payment->user_id}",
                ]),
                'metadata' => [
                    'paymentId' => (string) $payment->id,
                    'txRef' => $payment->tx_ref,
                ],
                'successUrl' => $this->returnUrl($payment),
                'gateway' => $gateway,
            ], fn ($value) => $value !== null));
        } catch (JamiException $e) {
            report($e);

            return CheckoutLaunch::failed();
        }

        // Keep the push details on the record so a pending overlay can show
        // which gateway/phone to check, even after a reload.
        if ($gateway !== null) {
            $data['direct'] = ['gateway' => $gateway, 'phone' => $phone];
        }

        $payment->update([
            'deposit_id' => $data['sessionId'] ?? null,
            'provider_response' => $data,
        ]);

        if (($data['mode'] ?? null) === 'redirect' && is_string($data['checkoutUrl'] ?? null)) {
            return CheckoutLaunch::redirect($data['checkoutUrl']);
        }

        return CheckoutLaunch::push();
    }

    /**
     * Build the post-payment URL carrying an encrypted payload with the
     * payment's identifiers, so the return page can decrypt it and know the
     * user, amount, provider and tx_ref without guessing.
     */
    private function returnUrl(Payment $payment): string
    {
        return rtrim((string) config('app.url'), '/').route('payment.callback', [
            'payload' => Crypt::encryptString(json_encode([
                'uid' => $payment->user_id,
                'tx_ref' => $payment->tx_ref,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider,
                'purpose' => $payment->purpose,
            ], JSON_THROW_ON_ERROR)),
        ], false);
    }
}
