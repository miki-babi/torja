<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ShopProduct;
use App\Support\ProgramCatalog;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public /explore catalog: every published exercise and meal program,
 * searchable by name. Guests can browse; saving still needs login and Pro —
 * cards reuse the shared-program save endpoints.
 */
class ExploreController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();
        $chip = collect(['workouts', 'nutrition', 'equipment', 'supplements'])
            ->first(fn (string $flag): bool => $request->query->has($flag), 'all');
        $user = $request->user();

        $exercisePrograms = ProgramCatalog::exercisePrograms($user, $search);
        $mealPrograms = ProgramCatalog::mealPrograms($user, $search);

        $products = ShopProduct::query()
            ->whereNotNull('published_at')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%"),
            ))
            ->latest('published_at')
            ->get();

        app(Tracker::class)->record('program_catalog.viewed', $user, [
            'exercise_programs' => count($exercisePrograms),
            'meal_programs' => count($mealPrograms),
            'search' => $search === '' ? null : $search,
            'chip' => $chip,
        ]);

        return Inertia::render('member/explore', [
            'exercisePrograms' => $exercisePrograms,
            'mealPrograms' => $mealPrograms,
            'products' => $products
                ->map(fn (ShopProduct $product): array => [
                    'key' => (string) $product->id,
                    'id' => $product->id,
                    'category' => $product->category,
                    'name' => $product->localizedName(),
                    'description' => $product->localizedDescription(),
                    'image_url' => $product->image_url,
                    'price_etb' => $product->price_etb,
                    'show_url' => route('shop.products.show', $product),
                ])
                ->all(),
            'isPro' => $user?->isPro() ?? false,
            'proUrl' => route('pro.show'),
            'filters' => ['q' => $search, 'chip' => $chip],
        ]);
    }

    /**
     * Public product detail page with the cash-on-delivery order form.
     */
    public function product(ShopProduct $product): Response
    {
        abort_unless($product->isPublished(), 404);

        return Inertia::render('member/product', [
            'product' => [
                'id' => $product->id,
                'name' => $product->localizedName(),
                'description' => $product->localizedDescription(),
                'image_url' => $product->image_url,
                'price_etb' => $product->price_etb,
                'content' => $product->content,
            ],
            'memberPhone' => auth()->user()?->phone,
            'storeUrl' => route('member.orders.store'),
            'loginUrl' => route('login'),
        ]);
    }
}
