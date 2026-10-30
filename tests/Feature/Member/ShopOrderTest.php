<?php

use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $product = ShopProduct::factory()->published()->create();

    $this->post(route('member.orders.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
        'phone' => '0912345678',
    ])->assertRedirect(route('login'));
});

test('members can place a cash on delivery order for a published product', function () {
    $user = User::factory()->create();
    $product = ShopProduct::factory()->published()->create([
        'price_etb' => 1500,
    ]);

    $this->actingAs($user)
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'phone' => '0912345678',
        ])
        ->assertRedirect();

    $order = ShopOrder::sole();

    expect($order->user_id)->toBe($user->id)
        ->and($order->quantity)->toBe(2)
        ->and($order->unit_price_etb)->toBe(1500)
        ->and($order->total_etb)->toBe(3000)
        ->and($order->phone)->toBe('+251912345678')
        ->and($order->status)->toBe('pending');
});

test('the delivery phone is remembered on the member account', function () {
    $user = User::factory()->create(['phone' => null]);
    $product = ShopProduct::factory()->published()->create();

    $this->actingAs($user)
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'phone' => '0912345678',
        ])
        ->assertRedirect();

    expect($user->fresh()->phone)->toBe('+251912345678')
        ->and(ShopOrder::sole()->phone)->toBe('+251912345678');
});

test('an existing member phone is not overwritten by a different delivery number', function () {
    $user = User::factory()->create(['phone' => '+251900000001']);
    $product = ShopProduct::factory()->published()->create();

    $this->actingAs($user)
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'phone' => '0912345678',
        ])
        ->assertRedirect();

    expect($user->fresh()->phone)->toBe('+251900000001')
        ->and(ShopOrder::sole()->phone)->toBe('+251912345678');
});

test('the saved member phone is used when none is submitted', function () {
    $user = User::factory()->create(['phone' => '+251911112222']);
    $product = ShopProduct::factory()->published()->create();

    $this->actingAs($user)
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertRedirect();

    expect(ShopOrder::sole()->phone)->toBe('+251911112222');
});

test('a delivery phone owned by another account is not claimed', function () {
    User::factory()->create(['phone' => '0912345678']);
    $user = User::factory()->create(['phone' => null]);
    $product = ShopProduct::factory()->published()->create();

    $this->actingAs($user)
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'phone' => '0912345678',
        ])
        ->assertRedirect();

    expect($user->fresh()->phone)->toBeNull()
        ->and(ShopOrder::sole()->phone)->toBe('+251912345678');
});

test('order placement redirects even for xhr requests', function () {
    $user = User::factory()->create();
    $product = ShopProduct::factory()->published()->create();

    $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->postJson(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'phone' => '0912345678',
        ])
        ->assertRedirect();

    expect(ShopOrder::sole()->user_id)->toBe($user->id);
});

test('unpublished products cannot be ordered', function () {
    $product = ShopProduct::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'phone' => '0912345678',
        ])
        ->assertNotFound();
});

test('order requires a valid quantity and phone', function () {
    $product = ShopProduct::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('member.orders.store'), [
            'product_id' => $product->id,
            'quantity' => 0,
            'phone' => '123',
        ])
        ->assertSessionHasErrors(['quantity', 'phone']);
});

test('members only see their own orders and can cancel pending ones', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = ShopOrder::factory()->for($user)->create();
    ShopOrder::factory()->for($other)->create();

    $this->actingAs($user)
        ->get(route('member.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/orders')
            ->has('orders', 1));

    $this->actingAs($other)
        ->post(route('member.orders.cancel', $mine))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('member.orders.cancel', $mine))
        ->assertRedirect();

    expect($mine->fresh()->status)->toBe('cancelled');
});

test('orders that already left pending cannot be cancelled', function () {
    $user = User::factory()->create();
    $order = ShopOrder::factory()->for($user)->create(['status' => 'delivered']);

    $this->actingAs($user)
        ->post(route('member.orders.cancel', $order))
        ->assertUnprocessable();
});

test('the product show page is public for published products', function () {
    $product = ShopProduct::factory()->published()->create([
        'name' => 'Jump rope',
        'price_etb' => 350,
    ]);

    $this->get(route('shop.products.show', $product))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/product')
            ->where('product.name', 'Jump rope')
            ->where('product.price_etb', 350)
            ->where('product.content', null));

    $draft = ShopProduct::factory()->create();
    $this->get(route('shop.products.show', $draft))->assertNotFound();
});
