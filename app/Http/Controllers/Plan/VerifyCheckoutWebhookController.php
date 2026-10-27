<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentSynchronizer;
use App\Services\Payments\VerifyCheckoutService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

/**
 * Verify Checkout's signed server-to-server webhook. Raw-body HMAC is
 * verified before any JSON parsing; accepted events are persisted to a
 * durable inbox (unique on provider+event_id) so at-least-once deliveries
 * collapse into one processing pass.
 */
class VerifyCheckoutWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 256 * 1024;

    public function __construct(
        private readonly VerifyCheckoutService $verifyCheckout,
        private readonly PaymentSynchronizer $synchronizer,
    ) {}

    /**
     * Accept a delivery, deduplicate it, and sync the affected payment.
     */
    public function store(Request $request): HttpResponse
    {
        abort_unless($request->isMethod('post') && $request->isJson(), 415);
        abort_if(strlen($request->getContent()) > self::MAX_BODY_BYTES, 413);
        abort_unless($this->verifyCheckout->verifyWebhookSignature($request), 401);

        /** @var array<string, mixed>|null $event */
        $event = json_decode($request->getContent(), true);

        $eventId = $event['id'] ?? null;
        $type = $event['type'] ?? null;

        // Header identifiers must match the signed body, and the event must
        // carry the minimum shape we act on.
        abort_unless(is_array($event)
            && is_string($eventId) && $eventId !== ''
            && is_string($type) && $type !== ''
            && $request->header('VerifyCheckout-Event-Id') === $eventId
            && $request->header('VerifyCheckout-Event') === $type, 422);

        try {
            WebhookEvent::create([
                'provider' => 'verify_checkout',
                'event_id' => $eventId,
                'type' => $type,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already accepted on an earlier delivery.
            return response()->noContent();
        }

        if ($type !== 'webhook.test') {
            $this->process($event);
        }

        return response()->noContent();
    }

    /**
     * Sync the payment matching the deposit in the event payload. Ordering
     * doesn't matter — the synchronizer fetches the authoritative deposit
     * state rather than trusting the event's snapshot.
     *
     * @param  array<string, mixed>  $event
     */
    private function process(array $event): void
    {
        $depositId = $event['data']['object']['id'] ?? null;

        if (! is_string($depositId) || $depositId === '') {
            return;
        }

        $payment = Payment::query()->where('deposit_id', $depositId)->first();

        if ($payment === null) {
            return;
        }

        $this->synchronizer->sync($payment);
    }
}
