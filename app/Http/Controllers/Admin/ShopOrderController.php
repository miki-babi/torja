<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fulfil COD shop orders: list with filters and advance the status
 * pending → confirmed → delivered, or cancel.
 */
class ShopOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        return Inertia::render('admin/shop-orders/index', [
            'orders' => ShopOrder::query()
                ->with(['user:id,name,phone', 'product:id,name,category'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest()
                ->limit(200)
                ->get()
                ->map(fn (ShopOrder $order): array => [
                    'id' => $order->id,
                    'member' => $order->user?->name ?? '—',
                    'product' => $order->product?->name ?? '—',
                    'category' => $order->product?->category,
                    'quantity' => $order->quantity,
                    'total_etb' => $order->total_etb,
                    'phone' => $order->phone,
                    'status' => $order->status,
                    'placed_at' => $order->created_at->format('Y-m-d H:i'),
                ])
                ->all(),
            'filters' => ['status' => $status],
        ]);
    }

    public function show(ShopOrder $shopOrder): Response
    {
        $shopOrder->load(['user:id,name,phone', 'product:id,name,category,image_path']);

        return Inertia::render('admin/shop-orders/show', [
            'order' => [
                'id' => $shopOrder->id,
                'member' => [
                    'id' => $shopOrder->user?->id,
                    'name' => $shopOrder->user?->name ?? '—',
                    'phone' => $shopOrder->user?->phone,
                ],
                'product' => [
                    'id' => $shopOrder->product?->id,
                    'name' => $shopOrder->product?->name ?? '—',
                    'category' => $shopOrder->product?->category,
                    'image_url' => $shopOrder->product?->image_url,
                ],
                'quantity' => $shopOrder->quantity,
                'unit_price_etb' => $shopOrder->unit_price_etb,
                'total_etb' => $shopOrder->total_etb,
                'phone' => $shopOrder->phone,
                'status' => $shopOrder->status,
                'placed_at' => $shopOrder->created_at->format('M j, Y · H:i'),
                'updated_at' => $shopOrder->updated_at->format('M j, Y · H:i'),
            ],
        ]);
    }

    public function update(Request $request, ShopOrder $shopOrder): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'confirmed', 'on_transit', 'delivered', 'returned', 'cancelled'])],
        ]);

        $ok = match ($data['status']) {
            'pending' => $shopOrder->reopen(),
            'confirmed' => $shopOrder->confirm(),
            'on_transit' => $shopOrder->transit(),
            'delivered' => $shopOrder->deliver(),
            'returned' => $shopOrder->markReturned(),
            'cancelled' => $shopOrder->cancel(),
        };

        if (! $ok) {
            return back()->withErrors(['status' => "Cannot mark a {$shopOrder->status} order as {$data['status']}."]);
        }

        return back();
    }
}
