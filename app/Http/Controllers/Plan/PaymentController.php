<?php

namespace App\Http\Controllers\Plan;

use App\Exceptions\Payments\ChapaException;
use App\Exceptions\Payments\JamiException;
use App\Exceptions\Payments\VerifyCheckoutException;
use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Payment;
use App\Services\Payments\ChapaService;
use App\Services\Payments\JamiService;
use App\Services\Payments\PaymentSynchronizer;
use App\Services\Payments\VerifyCheckoutService;
use App\Support\ProPage;
use App\Support\Tracking\Tracker;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ChapaService $chapa,
        private readonly VerifyCheckoutService $verifyCheckout,
        private readonly JamiService $jami,
        private readonly PaymentSynchronizer $synchronizer,
    ) {}

    /**
     * Start a checkout for the lifetime Pro unlock via the chosen provider.
     */
    public function store(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:chapa,verify_checkout,jami'],
            'gateway' => ['nullable', 'string', 'in:telebirr,mpesa,cbe'],
            'customer_phone' => ['required_with:gateway', 'nullable', 'string', 'min:9', 'max:16'],
        ]);

        $user = $request->user();

        if ($user->isPro()) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('You already have Pro.'),
            ]);

            return to_route('pro.show');
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => $validated['provider'],
            'tx_ref' => "tp-{$user->id}-".Str::uuid(),
            'idempotency_key' => 'checkout_'.Str::uuid(),
            'amount' => ProPage::price(),
            'currency' => 'ETB',
            'status' => 'pending',
            'purpose' => 'pro',
            'email' => $user->email ?? "user-{$user->id}@trainpro.local",
            'first_name' => Str::before(trim($user->name).' ', ' '),
            'last_name' => trim(Str::after(trim($user->name).' ', ' ')) ?: null,
        ]);

        app(Tracker::class)->record('checkout.started', $user, [
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
        ], $payment);

        if ($payment->isJami()) {
            return $this->startJami($payment, $validated['gateway'] ?? null, $validated['customer_phone'] ?? null);
        }

        $checkoutUrl = $payment->isVerifyCheckout()
            ? $this->startVerifyCheckout($payment)
            : $this->startChapa($payment);

        if ($checkoutUrl === null) {
            $payment->update(['status' => 'failed']);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('We could not start the payment. Please try again.'),
            ]);

            return to_route('pro.show');
        }

        return Inertia::location($checkoutUrl);
    }

    /**
     * Handle the customer redirect back from the hosted checkout.
     */
    public function callback(Request $request): RedirectResponse
    {
        $payment = $this->paymentFromReturn($request);

        if ($payment === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('We could not identify that payment. Please try again.'),
            ]);

            return to_route('pro.show');
        }

        $this->synchronizer->sync($payment);

        return $this->redirectFor($payment);
    }

    /**
     * Handle Chapa's server-to-server webhook (signature-verified, idempotent).
     */
    public function webhook(Request $request): HttpResponse
    {
        abort_unless($this->chapa->verifyWebhookSignature($request), 401);

        $txRef = $request->input('tx_ref') ?? $request->input('data.tx_ref');

        $payment = Payment::query()->where('tx_ref', $txRef)->first();

        if ($payment !== null) {
            $this->synchronizer->sync($payment);
        }

        return response()->noContent();
    }

    /**
     * Let a user re-check the status of their own payment (e.g. after a
     * bank delay before the callback/webhook arrives).
     */
    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->user_id !== $request->user()->id, 403);

        $this->synchronizer->sync($payment);

        return $this->redirectFor($payment, pending: in_array($payment->status, ['pending', 'review_required'], true));
    }

    /**
     * Lightweight JSON status for the Pro page's polling loop — syncs and
     * returns the local status without a redirect or flash.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        abort_if($payment->user_id !== $request->user()->id, 403);

        $this->synchronizer->sync($payment);

        return response()->json(['status' => $payment->fresh()->status]);
    }

    /**
     * Abandon a still-pending attempt so the buyer can start a fresh one.
     * If the provider later confirms it anyway (webhook or poll), the
     * synchronizer will still fulfill — paid is paid.
     */
    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->user_id !== $request->user()->id, 403);

        if (in_array($payment->status, ['pending', 'review_required'], true)) {
            $payment->update(['status' => 'cancelled']);

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Payment cancelled — you can try again.'),
            ]);
        }

        return to_route('pro.show');
    }

    /**
     * Redirect to the page relevant to what was bought: the shared program
     * for legacy program purchases, the Pro page for membership payments.
     */
    private function redirectFor(Payment $payment, bool $pending = false): RedirectResponse
    {
        if ($payment->purpose === 'program_purchase') {
            $program = $payment->payable;

            if ($program instanceof ExerciseProgram && $program->share_token !== null) {
                Inertia::flash('toast', $payment->isSuccessful()
                    ? ['type' => 'success', 'message' => __('Payment confirmed — the program is in your playlists.')]
                    : ['type' => $pending ? 'info' : 'error', 'message' => $pending
                        ? __('Payment is not confirmed yet. Please try again shortly.')
                        : __('Payment was not completed. You can try again.')]);

                return to_route('programs.shared.show', $program->share_token);
            }
        }

        if ($payment->purpose === 'meal_program_purchase') {
            $program = $payment->payable;

            if ($program instanceof MealProgram && $program->share_token !== null) {
                Inertia::flash('toast', $payment->isSuccessful()
                    ? ['type' => 'success', 'message' => __('Payment confirmed — the meal program is in your plans.')]
                    : ['type' => $pending ? 'info' : 'error', 'message' => $pending
                        ? __('Payment is not confirmed yet. Please try again shortly.')
                        : __('Payment was not completed. You can try again.')]);

                return to_route('meal-programs.shared.show', $program->share_token);
            }
        }

        Inertia::flash('toast', $payment->isSuccessful()
            ? ['type' => 'success', 'message' => __('Payment confirmed — welcome to Pro.')]
            : ['type' => $pending ? 'info' : 'error', 'message' => $pending
                ? __('Payment is not confirmed yet. Please try again shortly.')
                : __('Payment was not completed. You can try again.')]);

        return to_route('pro.show');
    }

    /**
     * Initialize a Chapa checkout and return the hosted checkout URL, or
     * null when Chapa rejects the request.
     */
    private function startChapa(Payment $payment): ?string
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
    private function startVerifyCheckout(Payment $payment): ?string
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
     * Create a Jami checkout session and route the buyer accordingly: a
     * payment link redirects to Jami's hosted page, while a direct push
     * prompts the buyer's phone and the Pro page polls for confirmation.
     * The session id is persisted in `deposit_id` for reconciliation.
     */
    private function startJami(Payment $payment, ?string $gateway, ?string $phone): Response|RedirectResponse
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

            $payment->update(['status' => 'failed']);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('We could not start the payment. Please try again.'),
            ]);

            return to_route('pro.show');
        }

        // Keep the push details on the record so the pending overlay can
        // show which gateway/phone to check, even after a reload.
        if ($gateway !== null) {
            $data['direct'] = ['gateway' => $gateway, 'phone' => $phone];
        }

        $payment->update([
            'deposit_id' => $data['sessionId'] ?? null,
            'provider_response' => $data,
        ]);

        if (($data['mode'] ?? null) === 'redirect' && is_string($data['checkoutUrl'] ?? null)) {
            return Inertia::location($data['checkoutUrl']);
        }

        return to_route('pro.show');
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

    /**
     * Resolve the payment from the encrypted return payload (preferred) or
     * the provider's appended tx_ref/trx_ref query parameter. Returns null
     * when the identifier is missing, undecryptable, or doesn't match the
     * payload.
     */
    private function paymentFromReturn(Request $request): ?Payment
    {
        /** @var array<string, mixed>|null $payload */
        $payload = null;

        $cipher = $request->query('payload');

        if (is_string($cipher) && $cipher !== '') {
            try {
                $decoded = json_decode(Crypt::decryptString($cipher), true, flags: JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            } catch (DecryptException|JsonException) {
                // Tampered or unreadable payload — fall back to tx_ref below.
            }
        }

        $txRef = $payload['tx_ref'] ?? $request->query('tx_ref') ?? $request->query('trx_ref');

        if (! is_string($txRef) || $txRef === '') {
            return null;
        }

        $payment = Payment::query()->where('tx_ref', $txRef)->first();

        if ($payment !== null && isset($payload['uid']) && (int) $payload['uid'] !== $payment->user_id) {
            return null;
        }

        return $payment;
    }
}
