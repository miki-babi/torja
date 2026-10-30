<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MealType;
use App\Http\Controllers\Admin\Concerns\StoresPublicImage;
use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\MealProgram;
use App\Models\MealProgramMeal;
use App\Models\ShortLink;
use App\Support\Tracking\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MealProgramController extends Controller
{
    use StoresPublicImage;

    /**
     * List all meal programs with day/meal/claim counts and share state.
     */
    public function index(): Response
    {
        return Inertia::render('admin/meal-programs/index', [
            'programs' => MealProgram::query()
                ->withCount(['meals', 'accessGrants'])
                ->latest()
                ->paginate(15)
                ->through(fn (MealProgram $program): array => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'price_etb' => $program->price_etb,
                    'is_free' => $program->isFree(),
                    'days_count' => $program->meals()->reorder()->distinct()->count('day'),
                    'meals_count' => $program->meals_count,
                    'claims_count' => $program->access_grants_count,
                    'share_url' => $program->shareUrl(),
                    'published' => $program->isPublished(),
                ]),
        ]);
    }

    /**
     * Blank meal program form.
     */
    public function create(): Response
    {
        return Inertia::render('admin/meal-programs/edit', [
            'program' => null,
            'foodSummaries' => new \stdClass,
            ...$this->metaProps(),
        ]);
    }

    /**
     * Create a meal program and its day tree in one submit.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $program = DB::transaction(function () use ($request, $data): MealProgram {
            $program = $request->user()->mealPrograms()->create([
                'name' => $data['name'],
                'name_am' => $data['name_am'] ?? null,
                'description' => $data['description'] ?? null,
                'description_am' => $data['description_am'] ?? null,
                'image_path' => $this->storeImage($request, 'meal-programs'),
                'highlights' => $data['highlights'] ?? null,
                'highlights_am' => $data['highlights_am'] ?? null,
                'price_etb' => $data['price_etb'],
                'published_at' => ($data['is_published'] ?? false) ? now() : null,
                'share_token' => ($data['is_published'] ?? false) ? (string) Str::uuid() : null,
            ]);

            $this->syncDays($program, $data['days'] ?? []);

            return $program;
        });

        app(Tracker::class)->record('admin.meal_program_created', subject: $program);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Meal program created.'),
        ]);

        return to_route('admin.meal-programs.edit', $program);
    }

    /**
     * Edit form for one meal program, including its day/meal tree.
     */
    public function edit(MealProgram $mealProgram): Response
    {
        $mealProgram->load('meals.items.food');

        return Inertia::render('admin/meal-programs/edit', [
            'program' => [
                'id' => $mealProgram->id,
                'name' => $mealProgram->name,
                'name_am' => $mealProgram->name_am,
                'description' => $mealProgram->description,
                'description_am' => $mealProgram->description_am,
                'image_url' => $mealProgram->image_url,
                'highlights' => $mealProgram->highlights ?? [],
                'highlights_am' => $mealProgram->highlights_am ?? [],
                'price_etb' => $mealProgram->price_etb,
                'share_url' => $mealProgram->shareUrl(),
                'published' => $mealProgram->isPublished(),
                'days' => $mealProgram->meals
                    ->groupBy('day')
                    ->map(fn ($meals): array => [
                        'meals' => $meals
                            ->map(fn (MealProgramMeal $meal): array => [
                                'id' => $meal->id,
                                'meal_type' => $meal->meal_type->value,
                                'items' => $meal->items
                                    ->map(fn ($item): array => [
                                        'food_id' => $item->food_id,
                                        'portion_note' => $item->portion_note,
                                    ])
                                    ->values()
                                    ->all(),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'foodSummaries' => $this->foodSummaries($mealProgram),
            ...$this->metaProps(),
        ]);
    }

    /**
     * Persist edits plus the full day/meal tree in one submit.
     */
    public function update(Request $request, MealProgram $mealProgram): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $mealProgram, $data): void {
            $mealProgram->update([
                'name' => $data['name'],
                'name_am' => $data['name_am'] ?? null,
                'description' => $data['description'] ?? null,
                'description_am' => $data['description_am'] ?? null,
                'image_path' => $this->storeImage($request, 'meal-programs', $mealProgram),
                'highlights' => $data['highlights'] ?? null,
                'highlights_am' => $data['highlights_am'] ?? null,
                'price_etb' => $data['price_etb'],
                'published_at' => ($data['is_published'] ?? $mealProgram->isPublished()) ? ($mealProgram->published_at ?? now()) : null,
                'share_token' => ($data['is_published'] ?? $mealProgram->isPublished()) ? ($mealProgram->share_token ?? (string) Str::uuid()) : $mealProgram->share_token,
            ]);

            $this->syncDays($mealProgram, $data['days'] ?? []);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Meal program updated.'),
        ]);

        return to_route('admin.meal-programs.edit', $mealProgram);
    }

    /**
     * Delete the meal program (cascades meals/items/claims; member copies
     * survive as member-owned meal plans).
     */
    public function destroy(MealProgram $mealProgram): RedirectResponse
    {
        $mealProgram->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Meal program deleted.'),
        ]);

        return to_route('admin.meal-programs.index');
    }

    /**
     * Enable sharing: mints (or reuses) the share token and returns the URL.
     */
    public function share(MealProgram $mealProgram): JsonResponse
    {
        $mealProgram->update([
            'share_token' => $mealProgram->share_token ?? (string) Str::uuid(),
        ]);

        app(Tracker::class)->record('admin.meal_program_shared', subject: $mealProgram);

        return response()->json(['share_url' => $mealProgram->shareUrl()]);
    }

    /**
     * Disable sharing: clears the token so the public link stops working.
     */
    public function unshare(MealProgram $mealProgram): JsonResponse
    {
        $mealProgram->update(['share_token' => null]);
        ShortLink::forget($mealProgram);

        app(Tracker::class)->record('admin.meal_program_unshared', subject: $mealProgram);

        return response()->json(['share_url' => null]);
    }

    /**
     * List the meal program in the public /explore catalog, minting a
     * share token when needed so the catalog cards have a detail URL.
     */
    public function publish(MealProgram $mealProgram): JsonResponse
    {
        $mealProgram->update([
            'published_at' => $mealProgram->published_at ?? now(),
            'share_token' => $mealProgram->share_token ?? (string) Str::uuid(),
        ]);

        app(Tracker::class)->record('admin.meal_program_published', subject: $mealProgram);

        return response()->json(['published' => true]);
    }

    /**
     * Remove the meal program from the public catalog. The share link
     * stays live — publishing and sharing are independent.
     */
    public function unpublish(MealProgram $mealProgram): JsonResponse
    {
        $mealProgram->update(['published_at' => null]);

        app(Tracker::class)->record('admin.meal_program_unpublished', subject: $mealProgram);

        return response()->json(['published' => false]);
    }

    /**
     * Meal-type options for the editor's slot picker.
     *
     * @return array{mealTypes: array<int, array{value: string, label: string}>}
     */
    private function metaProps(): array
    {
        return [
            'mealTypes' => collect(MealType::cases())
                ->map(fn (MealType $type): array => [
                    'value' => $type->value,
                    'label' => str($type->value)->replace('_', ' ')->title()->toString(),
                ])
                ->all(),
        ];
    }

    /**
     * Food display data for every item in the program, keyed by food id.
     *
     * @return array<int, array{name_en: string, kcal_per_100g: float|null, protein_g: float|null, carbs_g: float|null, fat_g: float|null}>
     */
    private function foodSummaries(MealProgram $mealProgram): array
    {
        $foodIds = $mealProgram->meals
            ->flatMap(fn (MealProgramMeal $meal) => $meal->items->pluck('food_id'))
            ->unique()
            ->all();

        return Food::query()
            ->whereIn('id', $foodIds)
            ->get(['id', 'name_en', 'image_path', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g'])
            ->keyBy('id')
            ->map(fn (Food $food): array => [
                'name_en' => $food->name_en,
                'image_url' => $food->image_url,
                'kcal_per_100g' => $food->kcal_per_100g === null ? null : (float) $food->kcal_per_100g,
                'protein_g' => $food->protein_g === null ? null : (float) $food->protein_g,
                'carbs_g' => $food->carbs_g === null ? null : (float) $food->carbs_g,
                'fat_g' => $food->fat_g === null ? null : (float) $food->fat_g,
            ])
            ->all();
    }

    /**
     * Validate the meal program form payload, including the nested day tree.
     *
     * @return array{name: string, name_am: string|null, description: string|null, description_am: string|null, price_etb: int, is_published?: bool, highlights?: array<int, string>, highlights_am?: array<int, string>, days?: array<int, array{meals?: array<int, array{id?: int, meal_type: string, items?: array<int, array{food_id: int, portion_note?: string|null}>}>}>}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'name_am' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_am' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'highlights' => ['nullable', 'array', 'max:10'],
            'highlights.*' => ['string', 'max:120'],
            'highlights_am' => ['nullable', 'array', 'max:10'],
            'highlights_am.*' => ['string', 'max:120'],
            'price_etb' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
            'days' => ['nullable', 'array', 'max:30'],
            'days.*.meals' => ['nullable', 'array', 'max:8'],
            'days.*.meals.*.id' => ['nullable', 'integer'],
            'days.*.meals.*.meal_type' => ['required', 'string', Rule::in(array_column(MealType::cases(), 'value'))],
            'days.*.meals.*.items' => ['nullable', 'array', 'max:20'],
            'days.*.meals.*.items.*.food_id' => ['required', 'integer', Rule::exists('foods', 'id')],
            'days.*.meals.*.items.*.portion_note' => ['nullable', 'string', 'max:120'],
        ]);
    }

    /**
     * Replace the program's day/meal tree with the submitted one: delete
     * removed meals, upsert the rest by (day, position), and rebuild each
     * meal's items (items carry no stable identity). Duplicate meal types
     * within a day are deduped last-wins so the unique index can't fail.
     *
     * @param  array<int, array{meals?: array<int, array{id?: int, meal_type: string, items?: array<int, array{food_id: int, portion_note?: string|null}>}>}>  $days
     */
    private function syncDays(MealProgram $program, array $days): void
    {
        $meals = collect($days)
            ->values()
            ->flatMap(fn (array $day, int $dayIndex) => collect($day['meals'] ?? [])
                ->mapWithKeys(fn (array $meal, int $position): array => [
                    (string) $meal['meal_type'] => [
                        'id' => $meal['id'] ?? null,
                        'day' => $dayIndex + 1,
                        'meal_type' => $meal['meal_type'],
                        'position' => $position,
                        'items' => $meal['items'] ?? [],
                    ],
                ])
                ->values(),
            )
            ->values();

        $keepIds = $meals->pluck('id')->filter()->all();

        $program->meals()->whereNotIn('id', $keepIds)->delete();

        foreach ($meals as $mealData) {
            $meal = isset($mealData['id'])
                ? $program->meals()->where('id', $mealData['id'])->first()
                : null;

            if ($meal === null) {
                $meal = $program->meals()->create([
                    'day' => $mealData['day'],
                    'meal_type' => $mealData['meal_type'],
                    'position' => $mealData['position'],
                ]);
            } else {
                $meal->update([
                    'day' => $mealData['day'],
                    'meal_type' => $mealData['meal_type'],
                    'position' => $mealData['position'],
                ]);
            }

            $meal->items()->delete();
            $meal->items()->createMany(
                collect($mealData['items'])
                    ->map(fn (array $item, int $position): array => [
                        'food_id' => $item['food_id'],
                        'portion_note' => $item['portion_note'] ?? null,
                        'position' => $position,
                    ])
                    ->all(),
            );
        }
    }
}
