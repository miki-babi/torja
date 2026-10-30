<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramAccess;
use App\Models\MealProgram;
use App\Models\MealProgramAccess;
use App\Models\MealProgramMeal;
use App\Models\ShopProduct;
use App\Models\User;
use App\Support\Tracking\Tracker;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $exercisePrograms = ExerciseProgram::query()
            ->whereNotNull('published_at')
            ->whereNotNull('share_token')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%"),
            ))
            ->withCount('workouts')
            ->latest('published_at')
            ->get();

        $mealPrograms = MealProgram::query()
            ->whereNotNull('published_at')
            ->whereNotNull('share_token')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%"),
            ))
            ->latest('published_at')
            ->get();

        $dayCounts = MealProgramMeal::query()
            ->whereIn('meal_program_id', $mealPrograms->modelKeys())
            ->get(['meal_program_id', 'day'])
            ->groupBy('meal_program_id')
            ->map(fn (Collection $rows): int => $rows->pluck('day')->unique()->count());

        $claimedPrograms = $this->claimedIds($user, $exercisePrograms->modelKeys(), $mealPrograms->modelKeys());

        $products = ShopProduct::query()
            ->whereNotNull('published_at')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%"),
            ))
            ->latest('published_at')
            ->get();

        $claimedPlans = $user !== null
            ? $user->mealPlans()
                ->whereIn('meal_program_id', $mealPrograms->modelKeys())
                ->pluck('id', 'meal_program_id')
            : collect();

        app(Tracker::class)->record('program_catalog.viewed', $user, [
            'exercise_programs' => $exercisePrograms->count(),
            'meal_programs' => $mealPrograms->count(),
            'search' => $search === '' ? null : $search,
            'chip' => $chip,
        ]);

        return Inertia::render('member/explore', [
            'exercisePrograms' => $exercisePrograms
                ->map(fn (ExerciseProgram $program): array => [
                    'key' => $program->share_token,
                    'kind' => 'exercise',
                    'name' => $program->localizedName(),
                    'description' => $program->localizedDescription(),
                    'image_url' => $program->image_url,
                    'meta_count' => $program->workouts_count,
                    'view_url' => route('programs.shared.show', $program->share_token),
                    'save_url' => route('programs.shared.save', $program->share_token),
                    'claimed' => $claimedPrograms['exercise']->contains($program->id),
                    'member_url' => $claimedPrograms['exercise']->contains($program->id)
                        ? route('member.programs.show', $program)
                        : route('member.exercise'),
                ])
                ->all(),
            'mealPrograms' => $mealPrograms
                ->map(fn (MealProgram $program): array => [
                    'key' => $program->share_token,
                    'kind' => 'meal',
                    'name' => $program->localizedName(),
                    'description' => $program->localizedDescription(),
                    'image_url' => $program->image_url,
                    'meta_count' => $dayCounts->get($program->id, 0),
                    'view_url' => route('meal-programs.shared.show', $program->share_token),
                    'save_url' => route('meal-programs.shared.save', $program->share_token),
                    'claimed' => $claimedPrograms['meal']->contains($program->id),
                    'member_url' => $claimedPlans->has($program->id)
                        ? route('member.meal-programs.show', $claimedPlans->get($program->id))
                        : null,
                ])
                ->all(),
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
     * The program ids the member already claimed, keyed by type.
     *
     * @param  array<int, int>  $exerciseProgramIds
     * @param  array<int, int>  $mealProgramIds
     * @return array{exercise: Collection<int, int>, meal: Collection<int, int>}
     */
    private function claimedIds(?User $user, array $exerciseProgramIds, array $mealProgramIds): array
    {
        if ($user === null) {
            return ['exercise' => collect(), 'meal' => collect()];
        }

        return [
            'exercise' => ExerciseProgramAccess::where('user_id', $user->id)
                ->whereIn('exercise_program_id', $exerciseProgramIds)
                ->pluck('exercise_program_id'),
            'meal' => MealProgramAccess::where('user_id', $user->id)
                ->whereIn('meal_program_id', $mealProgramIds)
                ->pluck('meal_program_id'),
        ];
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
