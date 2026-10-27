<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\JamiService;
use App\Services\Payments\PaymentSynchronizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

/**
 * Jami's signed server-to-server webhook. Raw-body HMAC is verified before
 * any JSON parsing; accepted events are persisted to a durable inbox
 * (unique on provider+event_id) so at-least-once deliveries collapse into
 * one processing pass.
 */
class JamiWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 256 * 1024;

    public function __construct(
        private readonly JamiService $jami,
        private readonly PaymentSynchronizer $synchronizer,
    ) {}

    /**
     * Accept a delivery, deduplicate it, and sync the affected payment.
     */
    public function store(Request $request): HttpResponse
    {
        abort_unless($request->isMethod('post') && $request->isJson(), 415);
        abort_if(strlen($request->getContent()) > self::MAX_BODY_BYTES, 413);
        abort_unless($this->jami->verifyWebhookSignature($request), 401);

        /** @var array<string, mixed>|null $event */
        $event = json_decode($request->getContent(), true);

        $eventId = $event['id'] ?? null;
        $type = $event['type'] ?? null;

        abort_unless(is_array($event)
            && is_string($eventId) && $eventId !== ''
            && is_string($type) && $type !== '', 422);

        try {
            WebhookEvent::create([
                'provider' => 'jami',
                'event_id' => $eventId,
                'type' => $type,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already accepted on an earlier delivery.
            return response()->noContent();
        }

        $this->process($event);

        return response()->noContent();
    }

    /**
     * React to the event by syncing the payment matching the session.
     * Ordering doesn't matter — the synchronizer fetches the authoritative
     * session state rather than trusting the event's snapshot.
     *
     * @param  array<string, mixed>  $event
     */
    private function process(array $event): void
    {
        $sessionId = $event['data']['sessionId'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $payment = Payment::query()->where('deposit_id', $sessionId)->first();

        if ($payment === null || $payment->isSuccessful()) {
            return;
        }

        if (($event['type'] ?? null) === 'checkout.session.expired') {
            $payment->update(['status' => 'failed']);

            return;
        }

        $this->synchronizer->sync($payment);
    }
}
