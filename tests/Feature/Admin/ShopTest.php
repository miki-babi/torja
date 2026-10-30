<?php

use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function shopAdmin(): User
{
    return User::factory()->admin()->create();
}

test('non admins cannot manage the shop', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.shop-products.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.shop-orders.index'))->assertForbidden();
});

test('admins can create, update and publish shop products', function () {
    $admin = shopAdmin();

    $this->actingAs($admin)
        ->post(route('admin.shop-products.store'), [
            'name' => 'Hex dumbbells 10kg',
            'description' => 'Solid pair.',
            'category' => 'equipment',
            'price_etb' => 2400,
            'content' => '<h2>Specs</h2><p>Steel pair.</p>',
            'published' => true,
        ])
        ->assertRedirect(route('admin.shop-products.index'));

    $product = ShopProduct::sole();
    expect($product->name)->toBe('Hex dumbbells 10kg')
        ->and($product->content)->toBe('<h2>Specs</h2><p>Steel pair.</p>')
        ->and($product->isPublished())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.shop-products.update', $product), [
            'name' => 'Hex dumbbells 12kg',
            'category' => 'equipment',
            'price_etb' => 2600,
            'published' => false,
        ])
        ->assertRedirect(route('admin.shop-products.index'));

    expect($product->fresh()->name)->toBe('Hex dumbbells 12kg')
        ->and($product->fresh()->isPublished())->toBeFalse();
});

test('product validation rejects bad categories and prices', function () {
    $this->actingAs(shopAdmin())
        ->post(route('admin.shop-products.store'), [
            'name' => 'X',
            'category' => 'clothes',
            'price_etb' => 0,
        ])
        ->assertSessionHasErrors(['category', 'price_etb']);
});

test('admins can advance order status through the allowed transitions', function () {
    $order = ShopOrder::factory()->create();

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'confirmed'])
        ->assertRedirect();
    expect($order->fresh()->status)->toBe('confirmed');

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'on_transit'])
        ->assertRedirect();
    expect($order->fresh()->status)->toBe('on_transit');

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'delivered'])
        ->assertRedirect();
    expect($order->fresh()->status)->toBe('delivered');
});

test('in-transit orders can be marked returned and reopened', function () {
    $order = ShopOrder::factory()->create(['status' => 'on_transit']);

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'returned'])
        ->assertRedirect();
    expect($order->fresh()->status)->toBe('returned');

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'pending'])
        ->assertRedirect();
    expect($order->fresh()->status)->toBe('pending');
});

test('admins can upload content images for the editor', function () {
    Storage::fake('public');

    $this->actingAs(shopAdmin())
        ->post(route('admin.shop-products.upload-image'), [
            'image' => UploadedFile::fake()->image('detail.jpg'),
        ])
        ->assertOk()
        ->assertJsonStructure(['url']);

    $this->actingAs(shopAdmin())
        ->post(route('admin.shop-products.upload-image'), [
            'image' => 'not-a-file',
        ])
        ->assertSessionHasErrors('image');
});

test('cancelled orders can be reopened to pending', function () {
    $order = ShopOrder::factory()->create(['status' => 'cancelled']);

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'pending'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('pending');
});

test('illegal order status transitions are rejected', function () {
    $order = ShopOrder::factory()->create(['status' => 'delivered']);

    $this->actingAs(shopAdmin())
        ->put(route('admin.shop-orders.update', $order), ['status' => 'cancelled'])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe('delivered');
});

test('admins see the order detail page', function () {
    $order = ShopOrder::factory()->create([
        'quantity' => 2,
        'unit_price_etb' => 500,
        'total_etb' => 1000,
        'status' => 'pending',
    ]);

    $this->actingAs(shopAdmin())
        ->get(route('admin.shop-orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/shop-orders/show')
            ->where('order.id', $order->id)
            ->where('order.member.name', $order->user->name)
            ->where('order.product.name', $order->product->name)
            ->where('order.unit_price_etb', 500)
            ->where('order.total_etb', 1000)
            ->where('order.status', 'pending'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.shop-orders.show', $order))
        ->assertForbidden();
});

test('orders index lists orders and filters by status', function () {
    ShopOrder::factory()->create(['status' => 'pending']);
    ShopOrder::factory()->create(['status' => 'delivered']);

    $this->actingAs(shopAdmin())
        ->get(route('admin.shop-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/shop-orders/index')
            ->has('orders', 2));

    $this->actingAs(shopAdmin())
        ->get(route('admin.shop-orders.index', ['status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1));
});
