<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIngredientRequest;
use App\Http\Requests\Admin\UpdateIngredientRequest;
use App\Models\Food;
use App\Models\Ingredient;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    /**
     * Searchable, paginated list of ingredients for data-quality editing.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/ingredients/index', [
            'ingredients' => Ingredient::query()
                ->select(['id', 'slug', 'name_en', 'name_am', 'category', 'common_allergy_label', 'show_in_onboarding', 'is_vegan'])
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('name_en', 'like', "%{$search}%")
                        ->orWhere('name_am', 'like', "%{$search}%")
                ))
                ->orderBy('name_en')
                ->paginate(25)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Blank ingredient form for a manual entry.
     */
    public function create(): Response
    {
        return Inertia::render('admin/ingredients/edit', [
            'ingredient' => null,
            ...$this->metaProps(),
        ]);
    }

    /**
     * Create a manual ingredient. Slugs are `custom-` prefixed so dataset
     * reseeds (which upsert by slug) can't collide with manual entries.
     */
    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $ingredient = Ingredient::create([
            ...$request->safe()->except(['show_in_onboarding', 'is_vegan']),
            'slug' => $this->uniqueSlug($request->input('name_en')),
            'allergens' => $request->input('allergens', []),
            'common_allergy_label' => $request->input('common_allergy_label'),
            'added_for' => $request->input('added_for'),
            'show_in_onboarding' => $request->boolean('show_in_onboarding'),
            'is_vegan' => $request->boolean('is_vegan'),
            'used_by_foods' => 0,
        ]);

        app(Tracker::class)->record('admin.ingredient_created', subject: $ingredient);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ingredient created.'),
        ]);

        return to_route('admin.ingredients.edit', $ingredient);
    }

    /**
     * Edit form for one ingredient.
     */
    public function edit(Ingredient $ingredient): Response
    {
        return Inertia::render('admin/ingredients/edit', [
            'ingredient' => $ingredient,
            ...$this->metaProps(),
        ]);
    }

    /**
     * Persist edits to one ingredient.
     */
    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $ingredient->update([
            ...$request->safe()->except(['show_in_onboarding', 'is_vegan']),
            'allergens' => $request->input('allergens', []),
            'show_in_onboarding' => $request->boolean('show_in_onboarding'),
            'is_vegan' => $request->boolean('is_vegan'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Ingredient updated.'),
        ]);

        return to_route('admin.ingredients.edit', $ingredient);
    }

    /**
     * Derive a collision-proof slug: `custom-` + slug(name), suffixed on dupes.
     */
    private function uniqueSlug(string $name): string
    {
        $base = 'custom-'.Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Ingredient::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Category and allergen options for the ingredient form.
     *
     * @return array<string, mixed>
     */
    private function metaProps(): array
    {
        return [
            'categories' => Ingredient::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'allergens' => collect(Food::ALLERGENS)
                ->map(fn (string $code): array => [
                    'value' => $code,
                    'label' => str($code)->replace('_', ' ')->title()->toString(),
                ])
                ->all(),
        ];
    }
}
