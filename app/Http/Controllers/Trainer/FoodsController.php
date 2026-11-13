<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\MealProgramItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The coach's private food library: foods they author and reuse inside
 * their own meal programs. The seeded EFCT dataset stays in the table as
 * invisible legacy (`user_id` null) — it backs seeded meal plans but is
 * never offered to coaches anymore.
 */
class FoodsController extends Controller
{
    /**
     * List the coach's own foods with search.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        return Inertia::render('trainer/foods/index', [
            'foods' => $request->user()->foods()
                ->when($search !== '', fn ($query) => $query
                    ->where(fn ($inner) => $inner
                        ->where('name_en', 'like', "%{$search}%")
                        ->orWhere('name_am', 'like', "%{$search}%")))
                ->orderBy('name_en')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Food $food): array => [
                    'id' => $food->id,
                    'name_en' => $food->name_en,
                    'name_am' => $food->name_am,
                    'category' => $food->category,
                    'kcal_per_100g' => $food->kcal_per_100g === null ? null : (float) $food->kcal_per_100g,
                    'protein_g' => $food->protein_g === null ? null : (float) $food->protein_g,
                    'image_url' => $food->image_url,
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Blank food form.
     */
    public function create(): Response
    {
        return Inertia::render('trainer/foods/edit', [
            'food' => null,
        ]);
    }

    /**
     * Create a food — dataset-only columns get inert defaults.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $request->user()->foods()->create([
            ...self::DATASET_DEFAULTS,
            'name_en' => $data['name_en'],
            'name_am' => $data['name_am'] ?? null,
            'category' => $data['category'] ?? 'custom',
            'kcal_per_100g' => $data['kcal_per_100g'] ?? null,
            'protein_g' => $data['protein_g'] ?? null,
            'carbs_g' => $data['carbs_g'] ?? null,
            'fat_g' => $data['fat_g'] ?? null,
            'fibre_g' => $data['fibre_g'] ?? null,
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('foods', 'public') ?: null
                : null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Food created.'),
        ]);

        return to_route('trainer.foods.index');
    }

    /**
     * Edit form for one food.
     */
    public function edit(Request $request, Food $food): Response
    {
        $this->authorizeOwner($request, $food);

        return Inertia::render('trainer/foods/edit', [
            'food' => [
                'id' => $food->id,
                'name_en' => $food->name_en,
                'name_am' => $food->name_am,
                'category' => $food->category,
                'kcal_per_100g' => $food->kcal_per_100g === null ? null : (float) $food->kcal_per_100g,
                'protein_g' => $food->protein_g === null ? null : (float) $food->protein_g,
                'carbs_g' => $food->carbs_g === null ? null : (float) $food->carbs_g,
                'fat_g' => $food->fat_g === null ? null : (float) $food->fat_g,
                'fibre_g' => $food->fibre_g === null ? null : (float) $food->fibre_g,
                'image_url' => $food->image_url,
            ],
        ]);
    }

    /**
     * Persist edits; a new image replaces the old file.
     */
    public function update(Request $request, Food $food): RedirectResponse
    {
        $this->authorizeOwner($request, $food);

        $data = $this->validated($request);

        $food->update([
            'name_en' => $data['name_en'],
            'name_am' => $data['name_am'] ?? null,
            'category' => $data['category'] ?? 'custom',
            'kcal_per_100g' => $data['kcal_per_100g'] ?? null,
            'protein_g' => $data['protein_g'] ?? null,
            'carbs_g' => $data['carbs_g'] ?? null,
            'fat_g' => $data['fat_g'] ?? null,
            'fibre_g' => $data['fibre_g'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $old = $food->image_path;
            $food->update([
                'image_path' => $request->file('image')->store('foods', 'public') ?: $old,
            ]);

            if ($old !== null) {
                Storage::disk('public')->delete($old);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Food updated.'),
        ]);

        return to_route('trainer.foods.index');
    }

    /**
     * Delete a food — blocked while meal programs still reference it, so
     * client copies never lose the entry silently.
     */
    public function destroy(Request $request, Food $food): RedirectResponse
    {
        $this->authorizeOwner($request, $food);

        if (MealProgramItem::query()->where('food_id', $food->id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This food is used in a meal program — remove it there first.'),
            ]);

            return back();
        }

        if ($food->image_path !== null) {
            Storage::disk('public')->delete($food->image_path);
        }
        $food->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Food deleted.'),
        ]);

        return to_route('trainer.foods.index');
    }

    /**
     * Dataset columns a coach-authored row fills with inert defaults.
     */
    private const DATASET_DEFAULTS = [
        'efct_code' => null,
        'allergens' => [],
        'meal_types' => [],
        'is_fasting_friendly' => false,
        'fish_ok_fasting' => false,
        'is_vegetarian' => false,
        'is_vegan' => false,
        'contains_meat' => false,
        'goal_fat_loss' => false,
        'goal_muscle_gain' => false,
        'goal_tag_basis' => '',
        'used_in_plans' => [],
        'source' => 'custom',
        'review_needed' => false,
    ];

    /**
     * Only the owning coach may touch a food here — dataset rows (user_id
     * null) and other coaches' foods 404.
     */
    private function authorizeOwner(Request $request, Food $food): void
    {
        abort_unless($food->isCustom() && $food->user_id === $request->user()->id, 404);
    }

    /**
     * @return array{name_en: string, name_am?: string|null, category?: string|null, kcal_per_100g?: string|null, protein_g?: string|null, carbs_g?: string|null, fat_g?: string|null, fibre_g?: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'name_am' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'kcal_per_100g' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'fibre_g' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
    }
}
