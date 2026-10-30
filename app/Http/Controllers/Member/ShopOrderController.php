<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cash-on-delivery shop orders: members place single-product orders from
 * Explore and manage them here. Cancel is only allowed while pending.
 */
class ShopOrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('member/orders', [
            'orders' => ShopOrder::query()
                ->where('user_id', $request->user()->id)
                ->with('product')
                ->latest()
                ->get()
                ->map(fn (ShopOrder $order): array => [
                    'id' => $order->id,
                    'product_name' => $order->product?->localizedName(),
                    'product_image' => $order->product?->image_url,
                    'category' => $order->product?->category,
                    'quantity' => $order->quantity,
                    'total_etb' => $order->total_etb,
                    'phone' => $order->phone,
                    'status' => $order->status,
                    'placed_at' => $order->created_at->toFormattedDateString(),
                    'can_cancel' => $order->isPending(),
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:shop_products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'phone' => ['nullable', 'string', 'min:9', 'max:20'],
        ]);

        $product = ShopProduct::findOrFail($data['product_id']);
        abort_unless($product->isPublished(), 404);

        $user = $request->user();
        $phone = PhoneNumber::normalize($data['phone'] ?? null) ?? $user->phone;

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => __('A delivery phone number is required.'),
            ]);
        }

        $order = ShopOrder::create([
            'user_id' => $user->id,
            'shop_product_id' => $product->id,
            'quantity' => $data['quantity'],
            'unit_price_etb' => $product->price_etb,
            'total_etb' => $product->price_etb * $data['quantity'],
            'phone' => $phone,
            'status' => 'pending',
        ]);

        app(Tracker::class)->record('shop_order.placed', $request->user(), [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'category' => $product->category,
            'quantity' => $order->quantity,
            'total_etb' => $order->total_etb,
        ], $order);

        // Remember the delivery phone on the account when the member doesn't
        // have one yet — never overwrite an existing login phone, and skip
        // numbers already owned by another account.
        if ($user->phone === null && ! User::where('phone', $phone)->exists()) {
            $user->update(['phone' => $phone]);
        }

        return back();
    }

    public function cancel(Request $request, ShopOrder $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->isPending(), 422);

        $order->cancel();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order cancelled.'),
        ]);

        return back();
    }
}
