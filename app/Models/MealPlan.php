<?php

namespace App\Models;

use App\Enums\FitnessGoal;
use Database\Factories\MealPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A multi-day meal plan. Rows with `user_id` null are the global plans
 * imported from plan PDFs and matched to profiles; rows with `user_id`
 * set are member-owned copies of a claimed MealProgram (provenance kept
 * in `meal_program_id`).
 *
 * @property int $id
 * @property string $slug
 * @property int|null $user_id
 * @property int|null $meal_program_id
 * @property string $name
 * @property FitnessGoal|null $goal
 * @property array<string, mixed> $tags
 * @property array<string, mixed>|null $meal_totals_stated
 * @property array<int, array{food_id: int, raw_text: string}>|null $optional_snacks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'user_id', 'meal_program_id', 'name', 'goal', 'tags', 'meal_totals_stated', 'optional_snacks'])]
class MealPlan extends Model
{
    /** @use HasFactory<MealPlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goal' => FitnessGoal::class,
            'tags' => 'array',
            'meal_totals_stated' => 'array',
            'optional_snacks' => 'array',
        ];
    }

    /**
     * The member who owns this plan copy, or null for the global seeded plans.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The meal program this plan was copied from, when it is a member claim.
     *
     * @return BelongsTo<MealProgram, $this>
     */
    public function sourceProgram(): BelongsTo
    {
        return $this->belongsTo(MealProgram::class, 'meal_program_id');
    }

    /**
     * The plan's meals, ordered by day then position.
     *
     * @return HasMany<MealPlanMeal, $this>
     */
    public function meals(): HasMany
    {
        return $this->hasMany(MealPlanMeal::class)
            ->orderBy('day')
            ->orderBy('position');
    }
}
