<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * JSON food search for the trainer meal-program editor — mirrors the
 * dataset search the admin food library used.
 */
class FoodSearchController extends Controller
{
    private const QUERY_ALIASES = [
        'injera' => 'enjera',
        'enjera' => 'injera',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        $foods = Food::query()
            ->when($query !== '', fn ($q) => $q->where(function ($w) use ($query) {
                foreach ($this->queryTerms($query) as $term) {
                    $w->orWhere('name_en', 'like', "%{$term}%")
                        ->orWhere('name_am', 'like', "%{$term}%");
                }
            }))
            ->orderBy('name_en')
            ->limit(20)
            ->get(['id', 'name_en', 'image_path', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g'])
            ->map(fn ($food): array => [...$food->toArray(), 'image_url' => $food->image_url]);

        return response()->json(['foods' => $foods]);
    }

    /**
     * @return array<int, string>
     */
    private function queryTerms(string $query): array
    {
        $terms = [$query];

        foreach (self::QUERY_ALIASES as $alias => $canonical) {
            if (str_contains(Str::lower($query), $alias)) {
                $terms[] = str_ireplace($alias, $canonical, $query);
            }
        }

        return array_values(array_unique($terms));
    }
}
