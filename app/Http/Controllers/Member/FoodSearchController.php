<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodSearchController extends Controller
{
    /**
     * Lightweight food search for the member "Log food" dialog.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $foods = Food::query()
            ->when($query !== '', fn ($q) => $q->where('name_en', 'like', "%{$query}%"))
            ->orderBy('name_en')
            ->limit(20)
            ->get(['id', 'name_en', 'image_path', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g'])
            ->map(fn ($food): array => [...$food->toArray(), 'image_url' => $food->image_url]);

        app(Tracker::class)->record('food.searched', properties: [
            'query' => $query === '' ? null : mb_substr($query, 0, 100),
            'result_count' => $foods->count(),
        ]);

        return response()->json(['foods' => $foods]);
    }
}
