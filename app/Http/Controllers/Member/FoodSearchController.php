<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FoodSearchController extends Controller
{
    /**
     * Transliteration spellings users swap interchangeably for the same food.
     *
     * @var array<string, string>
     */
    private const QUERY_ALIASES = [
        'injera' => 'enjera',
        'enjera' => 'injera',
    ];

    /**
     * Lightweight food search for the member "Log food" dialog.
     */
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

        app(Tracker::class)->record('food.searched', properties: [
            'query' => $query === '' ? null : mb_substr($query, 0, 100),
            'result_count' => $foods->count(),
        ]);

        return response()->json(['foods' => $foods]);
    }

    /**
     * The query plus transliteration variants worth matching — e.g.
     * "injera" also matches the dataset's "enjera" spellings.
     *
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
