<?php

namespace App\Services\Programs;

use App\Enums\MatchConfidence;
use App\Models\MealPlan;
use App\Models\MealProgram;
use App\Models\MealProgramAccess;
use App\Models\MealProgramMeal;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants a member access to a meal program by recording a claim row and
 * copying the program's day structure into the member's account as a
 * member-owned MealPlan. Copies are snapshots — later program edits don't
 * propagate.
 */
class MealProgramAccessGranter
{
    /**
     * Record the claim and copy the program's days into a member-owned
     * meal plan. Idempotent: if the member already holds a claim (free
     * save or earlier purchase) nothing is copied again and false is
     * returned.
     */
    public function grant(User $user, MealProgram $program, ?Payment $payment = null): bool
    {
        $program->loadMissing('meals.items.food');

        return DB::transaction(function () use ($user, $program, $payment): bool {
            $access = MealProgramAccess::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'meal_program_id' => $program->id,
                ],
                ['payment_id' => $payment?->id],
            );

            if (! $access->wasRecentlyCreated) {
                return false;
            }

            $this->copyPlan($user, $program);

            return true;
        });
    }

    /**
     * Whether the member already holds a claim on the program.
     */
    public function hasAccess(User $user, MealProgram $program): bool
    {
        return MealProgramAccess::where('user_id', $user->id)
            ->where('meal_program_id', $program->id)
            ->exists();
    }

    /**
     * The member's meal-plan copy of this program, when they have claimed it.
     */
    public function claimedPlan(User $user, MealProgram $program): ?MealPlan
    {
        return MealPlan::where('user_id', $user->id)
            ->where('meal_program_id', $program->id)
            ->first();
    }

    /**
     * Copy the program's days/meals/items into a member-owned meal plan.
     */
    private function copyPlan(User $user, MealProgram $program): void
    {
        $plan = MealPlan::create([
            'slug' => "prog-{$program->id}-u{$user->id}",
            'user_id' => $user->id,
            'meal_program_id' => $program->id,
            'name' => $program->name,
            'goal' => null,
            'tags' => [],
        ]);

        $program->meals->each(function (MealProgramMeal $programMeal) use ($plan): void {
            $meal = $plan->meals()->create([
                'day' => $programMeal->day,
                'meal_type' => $programMeal->meal_type,
                'position' => $programMeal->position,
            ]);

            $meal->items()->createMany(
                $programMeal->items
                    ->map(fn ($item): array => [
                        'food_id' => $item->food_id,
                        'raw_text' => $item->food?->name_en ?? 'Unknown food',
                        'portion_note' => $item->portion_note,
                        'match_confidence' => MatchConfidence::Exact,
                        'position' => $item->position,
                    ])
                    ->all(),
            );
        });
    }
}
