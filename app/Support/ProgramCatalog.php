<?php

namespace App\Support;

use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramAccess;
use App\Models\MealProgram;
use App\Models\MealProgramAccess;
use App\Models\MealProgramMeal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The published-program catalog shared by /explore and the wizard
 * "Discover programs" sections: localized card payloads carrying the
 * shared view/save urls, workout/day counts, and per-member claimed
 * flags so a saved program reads the same everywhere it is listed.
 */
class ProgramCatalog
{
    /**
     * Published and shared exercise programs, newest first.
     *
     * @return array<int, array{key: string, kind: string, name: string, description: string|null, image_url: string|null, meta_count: int, view_url: string, save_url: string, claimed: bool, member_url: string}>
     */
    public static function exercisePrograms(?User $user, string $search = '', ?int $authorId = null): array
    {
        $programs = ExerciseProgram::query()
            ->whereNotNull('published_at')
            ->whereNotNull('share_token')
            ->when($authorId !== null, fn ($query) => $query->where('user_id', $authorId))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%"),
            ))
            ->withCount('workouts')
            ->latest('published_at')
            ->get();

        $claimed = self::claimedIds($user, $programs->modelKeys(), []);

        return $programs
            ->map(fn (ExerciseProgram $program): array => [
                'key' => $program->share_token,
                'kind' => 'exercise',
                'name' => $program->localizedName(),
                'description' => $program->localizedDescription(),
                'image_url' => $program->image_url,
                'meta_count' => $program->workouts_count,
                'view_url' => route('programs.shared.show', $program->share_token),
                'save_url' => route('programs.shared.save', $program->share_token),
                'claimed' => $claimed['exercise']->contains($program->id),
                'member_url' => $claimed['exercise']->contains($program->id)
                    ? route('member.programs.show', $program)
                    : route('member.exercise'),
            ])
            ->all();
    }

    /**
     * Published and shared meal programs, newest first. `meta_count` is
     * the number of distinct plan days; `member_url` points at the
     * member's claimed plan when one exists.
     *
     * @return array<int, array{key: string, kind: string, name: string, description: string|null, image_url: string|null, meta_count: int, view_url: string, save_url: string, claimed: bool, member_url: string|null}>
     */
    public static function mealPrograms(?User $user, string $search = '', ?int $authorId = null): array
    {
        $programs = MealProgram::query()
            ->whereNotNull('published_at')
            ->whereNotNull('share_token')
            ->when($authorId !== null, fn ($query) => $query->where('user_id', $authorId))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_am', 'like', "%{$search}%"),
            ))
            ->latest('published_at')
            ->get();

        $dayCounts = MealProgramMeal::query()
            ->whereIn('meal_program_id', $programs->modelKeys())
            ->get(['meal_program_id', 'day'])
            ->groupBy('meal_program_id')
            ->map(fn (Collection $rows): int => $rows->pluck('day')->unique()->count());

        $claimed = self::claimedIds($user, [], $programs->modelKeys());

        $claimedPlans = $user !== null
            ? $user->mealPlans()
                ->whereIn('meal_program_id', $programs->modelKeys())
                ->pluck('id', 'meal_program_id')
            : collect();

        return $programs
            ->map(fn (MealProgram $program): array => [
                'key' => $program->share_token,
                'kind' => 'meal',
                'name' => $program->localizedName(),
                'description' => $program->localizedDescription(),
                'image_url' => $program->image_url,
                'meta_count' => $dayCounts->get($program->id, 0),
                'view_url' => route('meal-programs.shared.show', $program->share_token),
                'save_url' => route('meal-programs.shared.save', $program->share_token),
                'claimed' => $claimed['meal']->contains($program->id),
                'member_url' => $claimedPlans->has($program->id)
                    ? route('member.meal-programs.show', $claimedPlans->get($program->id))
                    : null,
            ])
            ->all();
    }

    /**
     * The program ids the member already claimed, keyed by type.
     *
     * @param  array<int, int>  $exerciseProgramIds
     * @param  array<int, int>  $mealProgramIds
     * @return array{exercise: Collection<int, int>, meal: Collection<int, int>}
     */
    private static function claimedIds(?User $user, array $exerciseProgramIds, array $mealProgramIds): array
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
}
