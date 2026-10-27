<?php

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.verify_checkout.api_key' => 'vchk-test',
        'services.verify_checkout.webhook_secret' => 'whsec-test',
        'services.verify_checkout.base_url' => 'https://checkoutapi.verify.et',
    ]);
});

/**
 * Build the signed headers for a webhook delivery carrying $body.
 *
 * @return array<string, string>
 */
function verifyWebhookHeaders(string $body, string $eventId = 'evt_1', string $type = 'deposit.succeeded', ?int $timestamp = null, string $secret = 'whsec-test'): array
{
    $timestamp ??= time();

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIFYCHECKOUT_EVENT' => $type,
        'HTTP_VERIFYCHECKOUT_EVENT_ID' => $eventId,
        'HTTP_VERIFYCHECKOUT_DELIVERY_ID' => 'del_1',
        'HTTP_VERIFYCHECKOUT_TIMESTAMP' => (string) $timestamp,
        'HTTP_VERIFYCHECKOUT_SIGNATURE' => 'v1='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret),
        'HTTP_VERIFYCHECKOUT_API_VERSION' => '2026-06-01',
    ];
}

test('verify checkout creates a pending payment with a deposit and redirects to the hosted checkout', function () {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits' => Http::response([
            'data' => [
                'id' => 'dep_123',
                'status' => 'awaiting_transfer',
                'checkout_url' => 'https://checkout.verify.et/c/token-123',
            ],
        ], 201),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('plan.checkout'), ['provider' => 'verify_checkout'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.verify.et/c/token-123');

    $payment = Payment::sole();
    expect($payment->provider)->toBe('verify_checkout')
        ->and($payment->deposit_id)->toBe('dep_123')
        ->and($payment->idempotency_key)->toStartWith('checkout_')
        ->and($payment->purpose)->toBe('pro')
        ->and($payment->status)->toBe('pending');

    Http::assertSent(fn ($request) => $request->url() === 'https://checkoutapi.verify.et/v1/deposits'
        && $request->hasHeader('Authorization', 'Bearer vchk-test')
        && $request->hasHeader('VerifyCheckout-Version', '2026-06-01')
        && $request->hasHeader('Idempotency-Key', $payment->idempotency_key)
        && $request['merchant_customer_id'] === "user-{$user->id}"
        && $request['currency'] === 'ETB'
        && is_string($request['return_url']));
});

test('an idempotent replay response is also accepted', function () {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits' => Http::response([
            'data' => [
                'id' => 'dep_replay',
                'status' => 'awaiting_transfer',
                'checkout_url' => 'https://checkout.verify.et/c/token-replay',
            ],
        ], 200),
    ]);

    $this->actingAs(User::factory()->create())
        ->withHeader('X-Inertia', 'true')
        ->post(route('plan.checkout'), ['provider' => 'verify_checkout'])
        ->assertStatus(409);

    expect(Payment::sole()->deposit_id)->toBe('dep_replay');
});

test('a rejected deposit creation marks the payment failed', function () {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits' => Http::response([
            'error' => ['code' => 'validation_failed', 'message' => 'return_url origin is not registered.'],
        ], 422),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('plan.checkout'), ['provider' => 'verify_checkout'])
        ->assertRedirect(route('pro.show'));

    expect(Payment::sole()->status)->toBe('failed');
});

test('checkout rejects an unknown provider without creating a payment', function () {
    Http::fake();
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->post(route('plan.checkout'), ['provider' => 'bogus'])
        ->assertSessionHasErrors('provider');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

test('the callback syncs a succeeded deposit and grants pro', function () {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits/*' => Http::response([
            'data' => ['id' => 'dep_ok', 'status' => 'succeeded'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->verifyCheckout()->create(['deposit_id' => 'dep_ok']);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success')
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($user->fresh()->isPro())->toBeTrue();
});

test('deposit statuses map onto local statuses without fulfilling', function (string $remote, string $local) {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits/*' => Http::response([
            'data' => ['id' => 'dep_st', 'status' => $remote],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->verifyCheckout()->create(['deposit_id' => 'dep_st']);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]));

    expect($payment->fresh()->status)->toBe($local)
        ->and($user->fresh()->isPro())->toBeFalse();
})->with([
    ['expired', 'expired'],
    ['cancelled', 'cancelled'],
    ['failed', 'failed'],
    ['review_required', 'review_required'],
    ['awaiting_transfer', 'pending'],
]);

test('users can re-verify their own verify checkout payment', function () {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits/*' => Http::response([
            'data' => ['id' => 'dep_own', 'status' => 'succeeded'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->verifyCheckout()->create(['deposit_id' => 'dep_own']);

    $this->actingAs($user)
        ->post(route('payment.verify', $payment))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success');
});

test('webhook rejects a bad signature', function () {
    Http::fake();
    Http::preventStrayRequests();

    $body = json_encode(['id' => 'evt_1', 'type' => 'deposit.succeeded', 'data' => ['object' => ['id' => 'dep_1']]]);

    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIFYCHECKOUT_EVENT' => 'deposit.succeeded',
        'HTTP_VERIFYCHECKOUT_EVENT_ID' => 'evt_1',
        'HTTP_VERIFYCHECKOUT_TIMESTAMP' => (string) time(),
        'HTTP_VERIFYCHECKOUT_SIGNATURE' => 'v1='.str_repeat('0', 64),
    ], $body)->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
    Http::assertNothingSent();
});

test('webhook rejects a stale timestamp', function () {
    $body = json_encode(['id' => 'evt_stale', 'type' => 'deposit.succeeded', 'data' => ['object' => ['id' => 'dep_1']]]);

    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [],
        verifyWebhookHeaders($body, 'evt_stale', timestamp: time() - 600),
        $body)->assertUnauthorized();
});

test('webhook rejects a header/body event mismatch', function () {
    $body = json_encode(['id' => 'evt_body', 'type' => 'deposit.succeeded', 'data' => ['object' => ['id' => 'dep_1']]]);

    $headers = verifyWebhookHeaders($body, 'evt_body');
    $headers['HTTP_VERIFYCHECKOUT_EVENT_ID'] = 'evt_other';

    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [], $headers, $body)
        ->assertStatus(422);
});

test('webhook fulfills a succeeded deposit and is exactly-once', function () {
    Http::fake([
        'https://checkoutapi.verify.et/v1/deposits/*' => Http::response([
            'data' => ['id' => 'dep_wh', 'status' => 'succeeded'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->verifyCheckout()->create(['deposit_id' => 'dep_wh']);

    $body = json_encode([
        'id' => 'evt_wh',
        'type' => 'deposit.succeeded',
        'data' => ['object' => ['id' => 'dep_wh', 'status' => 'succeeded']],
    ]);

    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [],
        verifyWebhookHeaders($body, 'evt_wh'), $body)->assertNoContent();

    expect($payment->fresh()->status)->toBe('success')
        ->and($user->fresh()->isPro())->toBeTrue()
        ->and(WebhookEvent::sole()->event_id)->toBe('evt_wh');

    // A repeat delivery is accepted as a duplicate without reprocessing.
    Http::fake();
    Http::preventStrayRequests();

    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [],
        verifyWebhookHeaders($body, 'evt_wh'), $body)->assertNoContent();

    expect(WebhookEvent::count())->toBe(1)
        ->and(Payment::sole()->status)->toBe('success');
});

test('a webhook.test delivery is accepted without touching payments', function () {
    $payment = Payment::factory()->verifyCheckout()->create();
    $body = json_encode(['id' => 'evt_test', 'type' => 'webhook.test', 'data' => ['object' => ['id' => $payment->deposit_id]]]);

    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [],
        verifyWebhookHeaders($body, 'evt_test', 'webhook.test'), $body)->assertNoContent();

    expect($payment->fresh()->status)->toBe('pending')
        ->and(WebhookEvent::sole()->type)->toBe('webhook.test');
});

test('webhook rejects non-json deliveries', function () {
    $this->call('POST', route('payment.verify-checkout.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], 'hello')->assertStatus(415);
});
