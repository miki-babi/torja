<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins can list and filter payments', function () {
    Payment::factory()->successful()->create(['email' => 'paid@example.com']);
    Payment::factory()->failed()->create(['email' => 'failed@example.com']);

    $this->actingAs($this->admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/index')
            ->has('payments.data', 2));

    $this->actingAs($this->admin)
        ->get(route('admin.payments.index', ['status' => 'success']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.email', 'paid@example.com'));

    $this->actingAs($this->admin)
        ->get(route('admin.payments.index', ['search' => 'failed@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1));
});

test('admins can view a payment with its provider response', function () {
    $payment = Payment::factory()->create([
        'provider_response' => ['status' => 'success', 'data' => ['foo' => 'bar']],
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.payments.show', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/show')
            ->where('payment.id', $payment->id)
            ->where('payment.provider_response.data.foo', 'bar'));
});

test('admins can re-verify a pending payment against its provider', function () {
    Http::fake([
        'https://api.chapa.co/v1/transaction/verify/*' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'success'],
        ]),
    ]);

    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->actingAs($this->admin)
        ->post(route('admin.payments.verify', $payment))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('success')
        ->and($payment->fresh()->paid_at)->not->toBeNull();
});

test('admins can refund a successful payment', function () {
    Http::fake([
        'https://api.chapa.co/v1/refund/*' => Http::response([
            'status' => 'success',
            'message' => 'Refund initiated',
        ]),
    ]);

    $payment = Payment::factory()->successful()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.payments.refund', $payment))
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe('refunded')
        ->and($payment->fresh()->refunded_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), "/refund/{$payment->tx_ref}"));
});

test('refunds are rejected for non-successful payments', function () {
    Http::fake();
    Http::preventStrayRequests();

    $payment = Payment::factory()->create(['status' => 'pending']);

    $this->actingAs($this->admin)
        ->post(route('admin.payments.refund', $payment))
        ->assertSessionHasErrors('refund');

    Http::assertNothingSent();
});
