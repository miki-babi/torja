<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MealType;
use App\Http\Controllers\Admin\Concerns\StoresPublicImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFoodRequest;
use App\Http\Requests\Admin\UpdateFoodRequest;
use App\Models\Food;
use App\Models\Ingredient;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FoodController extends Controller
{
    use StoresPublicImage;

    /**
     * Searchable, paginated list of foods for data-quality editing.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));

        return Inertia::render('admin/foods/index', [
            'foods' => Food::query()
                ->select(['id', 'name_en', 'name_am', 'category', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g', 'image_path', 'review_needed'])
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('name_en', 'like', "%{$search}%")
                        ->orWhere('name_am', 'like', "%{$search}%")
                ))
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->orderBy('name_en')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Food $food): array => [
                    ...$food->toArray(),
                    'image_url' => $food->image_url,
                ]),
            'categories' => Food::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => ['search' => $search, 'category' => $category],
        ]);
    }

    /**
     * Blank food form for a manual dataset entry.
     */
    public function create(): Response
    {
        return Inertia::render('admin/foods/edit', [
            'food' => null,
            ...$this->metaProps(),
        ]);
    }

    /**
     * Create a manual food row. Manual ids live at or above 90000 so dataset
     * reseeds (which upsert by explicit id) can never overwrite them.
     */
    public function store(StoreFoodRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image', 'ingredient_ids');

        $food = DB::transaction(function () use ($request, $data): Food {
            $food = new Food([
                ...$this->attributes($request, $data),
                'image_path' => $this->storeImage($request, 'foods'),
                'source' => 'Manual entry',
                'goal_tag_basis' => 'manual',
                'used_in_plans' => [],
            ]);
            $food->id = max((int) Food::max('id') + 1, 90000);
            $food->save();

            $this->syncIngredients($food, $request->input('ingredient_ids', []));

            return $food;
        });

        app(Tracker::class)->record('admin.food_created', subject: $food);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Food created.'),
        ]);

        return to_route('admin.foods.edit', $food);
    }

    /**
     * Edit form for one food's names, macros, flags and image.
     */
    public function edit(Food $food): Response
    {
        return Inertia::render('admin/foods/edit', [
            'food' => [
                ...$food->toArray(),
                'image_url' => $food->image_url,
                'ingredient_ids' => $food->ingredients()->pluck('ingredients.id')->all(),
            ],
            ...$this->metaProps(),
        ]);
    }

    /**
     * Persist edits to one food.
     */
    public function update(UpdateFoodRequest $request, Food $food): RedirectResponse
    {
        $data = $request->safe()->except('image', 'ingredient_ids');

        DB::transaction(function () use ($request, $food, $data): void {
            $food->update([
                ...$this->attributes($request, $data),
                'image_path' => $this->storeImage($request, 'foods', $food),
            ]);

            if ($request->has('ingredient_ids')) {
                $this->syncIngredients($food, $request->input('ingredient_ids', []));
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Food updated.'),
        ]);

        return to_route('admin.foods.edit', $food);
    }

    /**
     * Map validated input to model attributes, resolving checkbox booleans.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Request $request, array $data): array
    {
        $data['name_am'] ??= null;
        $data['data_fix_note'] ??= null;
        $data['meal_types'] ??= [];
        $data['allergens'] ??= [];

        foreach (['is_fasting_friendly', 'fish_ok_fasting', 'is_vegetarian', 'is_vegan', 'contains_meat', 'goal_fat_loss', 'goal_muscle_gain', 'review_needed'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }

    /**
     * Replace a food's ingredient links with the submitted ordered id list.
     *
     * @param  array<int, int|string>  $ingredientIds
     */
    private function syncIngredients(Food $food, array $ingredientIds): void
    {
        $food->ingredients()->sync(
            collect($ingredientIds)
                ->mapWithKeys(fn ($id, int $position): array => [
                    (int) $id => ['position' => $position],
                ])
                ->all(),
        );
    }

    /**
     * Options the food form needs: categories, meal types, allergen codes
     * and the ingredient picker list.
     *
     * @return array<string, mixed>
     */
    private function metaProps(): array
    {
        return [
            'categories' => Food::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'mealTypes' => collect(MealType::cases())
                ->map(fn (MealType $type): array => [
                    'value' => $type->value,
                    'label' => str($type->value)->replace('_', ' ')->title()->toString(),
                ])
                ->all(),
            'allergens' => collect(Food::ALLERGENS)
                ->map(fn (string $code): array => [
                    'value' => $code,
                    'label' => str($code)->replace('_', ' ')->title()->toString(),
                ])
                ->all(),
            'ingredientOptions' => Ingredient::query()
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_am']),
        ];
    }
}
