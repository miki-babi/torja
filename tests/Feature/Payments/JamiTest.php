<?php

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.jami.token' => 'jamidev-test',
        'services.jami.webhook_secret' => 'jami-whsec-test',
        'services.jami.base_url' => 'https://jami.bio',
    ]);
});

/**
 * Build the signed headers for a webhook delivery carrying $body.
 *
 * @return array<string, string>
 */
function jamiWebhookHeaders(string $body, string $eventId = 'evt_1', string $type = 'order.completed', ?int $timestamp = null, string $secret = 'jami-whsec-test'): array
{
    $timestamp ??= time();

    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_JAMIDEV_EVENT' => $type,
        'HTTP_X_JAMIDEV_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret),
    ];
}

test('jami creates a pending payment and redirects to the hosted payment link', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c00',
            'checkoutUrl' => 'https://jami.bio/jamidev/pay/cs_abc123',
            'mode' => 'redirect',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('plan.checkout'), ['provider' => 'jami'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://jami.bio/jamidev/pay/cs_abc123');

    $payment = Payment::sole();
    expect($payment->provider)->toBe('jami')
        ->and($payment->deposit_id)->toBe('6710c2e8b1a2f0012ab34c00')
        ->and($payment->purpose)->toBe('pro')
        ->and($payment->status)->toBe('pending');

    Http::assertSent(fn ($request) => $request->url() === 'https://jami.bio/api/jamidev/checkout'
        && $request->hasHeader('Authorization', 'Bearer jamidev-test')
        && $request['amount'] === (int) round((float) $payment->amount * 100)
        && $request['customer']['email'] === $payment->email
        && $request['customer']['externalId'] === "user-{$user->id}"
        && ! isset($request['gateway'])
        && is_string($request['successUrl']));
});

test('a direct push sends the gateway and phone and keeps the payment pending', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c01',
            'checkoutUrl' => null,
            'mode' => 'direct',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('plan.checkout'), [
            'provider' => 'jami',
            'gateway' => 'telebirr',
            'customer_phone' => '251900000001',
        ])
        ->assertRedirect(route('pro.show'));

    $payment = Payment::sole();
    expect($payment->status)->toBe('pending')
        ->and($payment->deposit_id)->toBe('6710c2e8b1a2f0012ab34c01')
        ->and($payment->provider_response['direct']['gateway'])->toBe('telebirr')
        ->and($payment->provider_response['direct']['phone'])->toBe('251900000001');

    Http::assertSent(fn ($request) => $request['gateway'] === 'telebirr'
        && $request['customer']['phone'] === '251900000001');
});

test('a gateway without a phone is rejected', function () {
    Http::fake();
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->post(route('plan.checkout'), [
            'provider' => 'jami',
            'gateway' => 'telebirr',
        ])
        ->assertSessionHasErrors('customer_phone');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

test('a rejected checkout creation marks the payment failed', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout' => Http::response([
            'error' => ['message' => 'Bad token'],
        ], 401),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('plan.checkout'), ['provider' => 'jami'])
        ->assertRedirect(route('pro.show'));

    expect(Payment::sole()->status)->toBe('failed');
});

test('the callback syncs a paid jami order and grants pro', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c02',
            'status' => 'completed',
            'orderId' => '6710c2e8b1a2f0012ab34c11',
            'orderStatus' => 'paid',
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c02']);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success')
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($user->fresh()->isPro())->toBeTrue();
});

test('an open session stays pending and an expired one fails', function (string $remote, string $local) {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c03',
            'status' => $remote,
            'orderStatus' => $remote === 'completed' ? 'pending' : null,
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c03']);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]));

    expect($payment->fresh()->status)->toBe($local)
        ->and($user->fresh()->isPro())->toBeFalse();
})->with([
    ['open', 'pending'],
    ['completed', 'pending'],
    ['expired', 'failed'],
]);

test('users can re-verify their own jami payment', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c04',
            'status' => 'completed',
            'orderStatus' => 'paid',
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c04']);

    $this->actingAs($user)
        ->post(route('payment.verify', $payment))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success');
});

test('the status endpoint syncs and returns json without redirecting', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c07',
            'status' => 'completed',
            'orderStatus' => 'paid',
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c07']);

    $this->actingAs($user)
        ->getJson(route('payment.status', $payment))
        ->assertOk()
        ->assertJson(['status' => 'success']);
});

test('the status endpoint is owner-only', function () {
    Http::fake();
    Http::preventStrayRequests();

    $payment = Payment::factory()->jami()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('payment.status', $payment))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('re-verifying a failed payment shows the failure toast, not the pending one', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c08',
            'status' => 'expired',
            'orderStatus' => null,
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c08']);

    $this->actingAs($user)
        ->post(route('payment.verify', $payment))
        ->assertRedirect(route('pro.show'));

    $payment->refresh();
    expect($payment->status)->toBe('failed')
        ->and(session('inertia.flash_data.toast.type'))->toBe('error');
});

test('a user can cancel their own pending payment to retry', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create();

    $this->actingAs($user)
        ->post(route('payment.cancel', $payment))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('cancelled');
});

test('cancel does not touch resolved payments or other users payments', function () {
    $owner = User::factory()->create();
    $successful = Payment::factory()->for($owner)->jami()->successful()->create();
    $pending = Payment::factory()->for($owner)->jami()->create();

    $this->actingAs($owner)
        ->post(route('payment.cancel', $successful))
        ->assertRedirect(route('pro.show'));

    expect($successful->fresh()->status)->toBe('success');

    $this->actingAs(User::factory()->create())
        ->post(route('payment.cancel', $pending))
        ->assertForbidden();

    expect($pending->fresh()->status)->toBe('pending');
});

test('a cancelled payment still fulfills if the provider later confirms it', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c09',
            'status' => 'completed',
            'orderStatus' => 'paid',
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create([
        'deposit_id' => '6710c2e8b1a2f0012ab34c09',
        'status' => 'cancelled',
    ]);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]));

    expect($payment->fresh()->status)->toBe('success')
        ->and($user->fresh()->isPro())->toBeTrue();
});

test('the pro page exposes the pending jami payment with a masked phone', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c0a',
            'status' => 'open',
            'orderStatus' => null,
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create([
        'deposit_id' => '6710c2e8b1a2f0012ab34c0a',
        'provider_response' => [
            'sessionId' => '6710c2e8b1a2f0012ab34c0a',
            'mode' => 'direct',
            'direct' => ['gateway' => 'telebirr', 'phone' => '251911223344'],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('pro.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingJamiPayment.id', $payment->id)
            ->where('pendingJamiPayment.gateway', 'telebirr')
            ->where('pendingJamiPayment.phone', '2519•••••344')
            ->etc());
});

test('the pro page resumes an abandoned payment link', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c0b',
            'status' => 'open',
            'orderStatus' => null,
        ]),
    ]);

    $user = User::factory()->create();
    Payment::factory()->for($user)->jami()->create([
        'deposit_id' => '6710c2e8b1a2f0012ab34c0b',
        'provider_response' => [
            'sessionId' => '6710c2e8b1a2f0012ab34c0b',
            'mode' => 'redirect',
            'checkoutUrl' => 'https://jami.bio/jamidev/pay/cs_resume',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('pro.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingJamiPayment.checkoutUrl', 'https://jami.bio/jamidev/pay/cs_resume')
            ->where('pendingJamiPayment.phone', null)
            ->etc());
});

test('webhook rejects a bad signature', function () {
    Http::fake();
    Http::preventStrayRequests();

    $body = json_encode(['id' => 'evt_1', 'type' => 'order.completed', 'data' => ['sessionId' => 's_1']]);

    $this->call('POST', route('payment.jami.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_JAMIDEV_EVENT' => 'order.completed',
        'HTTP_X_JAMIDEV_SIGNATURE' => 't='.time().',v1='.str_repeat('0', 64),
    ], $body)->assertUnauthorized();

    expect(WebhookEvent::count())->toBe(0);
    Http::assertNothingSent();
});

test('webhook rejects a stale timestamp', function () {
    $body = json_encode(['id' => 'evt_stale', 'type' => 'order.completed', 'data' => ['sessionId' => 's_1']]);

    $this->call('POST', route('payment.jami.webhook'), [], [], [],
        jamiWebhookHeaders($body, 'evt_stale', timestamp: time() - 600),
        $body)->assertUnauthorized();
});

test('webhook fulfills a completed order and is exactly-once', function () {
    Http::fake([
        'https://jami.bio/api/jamidev/checkout/*' => Http::response([
            'sessionId' => '6710c2e8b1a2f0012ab34c05',
            'status' => 'completed',
            'orderStatus' => 'paid',
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c05']);

    $body = json_encode([
        'id' => 'evt_wh',
        'type' => 'order.completed',
        'data' => ['sessionId' => '6710c2e8b1a2f0012ab34c05', 'status' => 'paid'],
    ]);

    $this->call('POST', route('payment.jami.webhook'), [], [], [],
        jamiWebhookHeaders($body, 'evt_wh'), $body)->assertNoContent();

    expect($payment->fresh()->status)->toBe('success')
        ->and($user->fresh()->isPro())->toBeTrue()
        ->and(WebhookEvent::sole()->event_id)->toBe('evt_wh');

    // A repeat delivery is accepted as a duplicate without reprocessing.
    Http::fake();
    Http::preventStrayRequests();

    $this->call('POST', route('payment.jami.webhook'), [], [], [],
        jamiWebhookHeaders($body, 'evt_wh'), $body)->assertNoContent();

    expect(WebhookEvent::count())->toBe(1)
        ->and(Payment::sole()->status)->toBe('success');
});

test('an expired session webhook marks the payment failed', function () {
    Http::fake();

    $payment = Payment::factory()->jami()->create(['deposit_id' => '6710c2e8b1a2f0012ab34c06']);

    $body = json_encode([
        'id' => 'evt_exp',
        'type' => 'checkout.session.expired',
        'data' => ['sessionId' => '6710c2e8b1a2f0012ab34c06', 'failure' => ['code' => 'payment_timed_out']],
    ]);

    $this->call('POST', route('payment.jami.webhook'), [], [], [],
        jamiWebhookHeaders($body, 'evt_exp', 'checkout.session.expired'), $body)->assertNoContent();

    expect($payment->fresh()->status)->toBe('failed')
        ->and(WebhookEvent::sole()->type)->toBe('checkout.session.expired');
});

test('webhook rejects non-json deliveries', function () {
    $this->call('POST', route('payment.jami.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], 'hello')->assertStatus(415);
});
