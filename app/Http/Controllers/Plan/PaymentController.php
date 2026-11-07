<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Payment;
use App\Services\Payments\ChapaService;
use App\Services\Payments\CheckoutLauncher;
use App\Services\Payments\PaymentSynchronizer;
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
        private readonly PaymentSynchronizer $synchronizer,
        private readonly CheckoutLauncher $launcher,
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

        if ($user->isPaidPro()) {
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

        $launch = $this->launcher->start(
            $payment,
            $validated['gateway'] ?? null,
            $validated['customer_phone'] ?? null,
        );

        if ($launch->checkoutUrl !== null) {
            return Inertia::location($launch->checkoutUrl);
        }

        if ($launch->pushInitiated) {
            return to_route('pro.show');
        }

        $payment->update(['status' => 'failed']);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('We could not start the payment. Please try again.'),
        ]);

        return to_route('pro.show');
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
