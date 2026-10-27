<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.chapa.webhook_secret' => 'test-webhook-secret']);
});

test('checkout creates a pending payment and redirects to the Chapa checkout url', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/test-123'],
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post(route('plan.checkout'), ['provider' => 'chapa']);

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://checkout.chapa.co/test-123');

    $payment = Payment::sole();
    expect($payment->user_id)->toBe($user->id)
        ->and($payment->status)->toBe('pending')
        ->and($payment->tx_ref)->toStartWith("tp-{$user->id}-")
        ->and((float) $payment->amount)->toBe(500.0);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.chapa.co/v1/transaction/initialize')
        && $request['tx_ref'] === $payment->tx_ref
        && $request['email'] === $user->email);
});

test('checkout is blocked for members who already have pro', function () {
    Http::fake();
    Http::preventStrayRequests();

    $user = User::factory()->create();
    Payment::factory()->for($user)->successful()->create();

    $this->actingAs($user)
        ->post(route('plan.checkout'), ['provider' => 'chapa'])
        ->assertRedirect(route('pro.show'));

    expect(Payment::count())->toBe(1);
    Http::assertNothingSent();
});

test('callback marks a verified payment as successful and is idempotent', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create(['status' => 'pending']);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success')
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($user->fresh()->isPro())->toBeTrue();

    // Second visit is idempotent and does not call Chapa again.
    Http::fake();
    Http::preventStrayRequests();

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    Http::assertNothingSent();
});

test('callback marks a failed verification as failed', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'failed'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create(['status' => 'pending']);

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => $payment->tx_ref]))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('failed');
});

test('callback redirects to the pro page for an unknown or missing tx_ref', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('payment.callback', ['tx_ref' => 'does-not-exist']))
        ->assertRedirect(route('pro.show'));

    $this->actingAs($user)
        ->get(route('payment.callback'))
        ->assertRedirect(route('pro.show'));
});

test('callback resolves the payment from the encrypted return payload', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create(['status' => 'pending']);

    $payload = Crypt::encryptString(json_encode([
        'uid' => $user->id,
        'tx_ref' => $payment->tx_ref,
        'amount' => (float) $payment->amount,
        'purpose' => 'pro',
    ]));

    $this->actingAs($user)
        ->get(route('payment.callback', ['payload' => $payload]))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success');
});

test('callback rejects a payload whose uid does not match the payment', function () {
    Http::fake();
    Http::preventStrayRequests();

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create(['status' => 'pending']);

    $payload = Crypt::encryptString(json_encode([
        'uid' => $user->id + 999,
        'tx_ref' => $payment->tx_ref,
    ]));

    $this->actingAs($user)
        ->get(route('payment.callback', ['payload' => $payload]))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('pending');
});

test('checkout sends a ciphered return url to chapa', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/test-123'],
        ]),
    ]);

    $this->actingAs(User::factory()->create())->post(route('plan.checkout'), ['provider' => 'chapa']);

    Http::assertSent(function ($request) {
        $query = [];
        parse_str((string) parse_url($request['return_url'], PHP_URL_QUERY), $query);

        $payload = json_decode(Crypt::decryptString($query['payload']), true);

        return $request['callback_url'] === $request['return_url']
            && $payload['uid'] === Payment::sole()->user_id
            && $payload['tx_ref'] === Payment::sole()->tx_ref
            && (float) $payload['amount'] === 500.0;
    });
});

test('webhook rejects a bad signature', function () {
    Http::fake();
    Http::preventStrayRequests();

    $body = json_encode(['tx_ref' => 'tp-1-abc', 'status' => 'success']);

    $this->call('POST', route('payment.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CHAPA_SIGNATURE' => 'bad-signature',
    ], $body)->assertUnauthorized();

    Http::assertNothingSent();
});

test('webhook with a valid signature verifies and marks the payment paid', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $payment = Payment::factory()->create(['status' => 'pending']);
    $body = json_encode(['tx_ref' => $payment->tx_ref, 'status' => 'success']);
    $signature = hash_hmac('sha256', $body, 'test-webhook-secret');

    $this->call('POST', route('payment.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CHAPA_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    expect($payment->fresh()->status)->toBe('success')
        ->and($payment->fresh()->paid_at)->not->toBeNull();
});

test('users can re-verify their own payment', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $user = User::factory()->create();
    $payment = Payment::factory()->for($user)->create(['status' => 'pending']);

    $this->actingAs($user)
        ->post(route('payment.verify', $payment))
        ->assertRedirect(route('pro.show'));

    expect($payment->fresh()->status)->toBe('success');
});

test('users cannot re-verify someone elses payment', function () {
    Http::fake();
    Http::preventStrayRequests();

    $payment = Payment::factory()->create(['status' => 'pending']);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('payment.verify', $payment))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('a failed chapa initialize marks the payment failed and redirects', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'failed',
            'message' => 'Invalid key',
        ], 400),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('plan.checkout'), ['provider' => 'chapa'])
        ->assertRedirect(route('pro.show'));

    expect(Payment::sole()->status)->toBe('failed');
});

test('a chapa validation error with an array message does not crash', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'failed',
            'message' => [
                'customization.title' => ['The customization.title may not be greater than 16 characters.'],
            ],
        ], 400),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('plan.checkout'), ['provider' => 'chapa'])
        ->assertRedirect(route('pro.show'));

    expect(Payment::sole()->status)->toBe('failed');
});

test('a legacy program purchase does not grant pro', function () {
    $user = User::factory()->create();
    Payment::factory()->for($user)->successful()->create([
        'purpose' => 'program_purchase',
    ]);

    expect($user->isPro())->toBeFalse();
});
