<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShopProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/shop-products/index', [
            'products' => ShopProduct::query()
                ->withCount('orders')
                ->latest()
                ->get()
                ->map(fn (ShopProduct $p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'category' => $p->category,
                    'price_etb' => $p->price_etb,
                    'image_url' => $p->image_url,
                    'published' => $p->isPublished(),
                    'orders_count' => $p->orders_count,
                ])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/shop-products/edit', ['product' => null]);
    }

    public function edit(ShopProduct $shopProduct): Response
    {
        return Inertia::render('admin/shop-products/edit', [
            'product' => [
                'id' => $shopProduct->id,
                'name' => $shopProduct->name,
                'name_am' => $shopProduct->name_am,
                'description' => $shopProduct->description,
                'description_am' => $shopProduct->description_am,
                'category' => $shopProduct->category,
                'price_etb' => $shopProduct->price_etb,
                'content' => $shopProduct->content,
                'image_url' => $shopProduct->image_url,
                'published' => $shopProduct->isPublished(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Log::info('shop-product.store: incoming', [
            'user' => $request->user()?->id,
            'payload' => $request->except('image'),
            'has_file' => $request->hasFile('image'),
        ]);

        $data = $this->validated($request);
        $data['image_path'] = $this->storeImage($request);

        $product = ShopProduct::create($data + [
            'published_at' => $request->boolean('published') ? now() : null,
        ]);

        Log::info('shop-product.store: created', ['id' => $product->id]);

        return to_route('admin.shop-products.index');
    }

    public function update(Request $request, ShopProduct $shopProduct): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        $shopProduct->update($data);
        $shopProduct->update([
            'published_at' => $request->boolean('published')
                ? ($shopProduct->published_at ?? now())
                : null,
        ]);

        return to_route('admin.shop-products.index');
    }

    /**
     * Image picked inside the rich text editor — returns a public URL to
     * embed in the product's HTML content.
     */
    public function uploadImage(Request $request): array
    {
        $request->validate(['image' => ['required', 'image', 'max:4096']]);

        $path = $request->file('image')->store('shop-products/content', 'public');

        return ['url' => asset('storage/'.$path)];
    }

    private function storeImage(Request $request): ?string
    {
        return $request->hasFile('image')
            ? $request->file('image')->store('shop-products', 'public')
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'description_am' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:50000'],
            'category' => ['required', 'string', Rule::in(['equipment', 'supplement'])],
            'price_etb' => ['required', 'integer', 'min:1', 'max:10000000'],
            'published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
    }
}
