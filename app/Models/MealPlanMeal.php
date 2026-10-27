<?php

namespace App\Models;

use App\Enums\MealType;
use Database\Factories\MealPlanMealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One meal slot within a day of a meal plan.
 *
 * @property int $id
 * @property int $meal_plan_id
 * @property int $day
 * @property MealType $meal_type
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['meal_plan_id', 'day', 'meal_type', 'position'])]
class MealPlanMeal extends Model
{
    /** @use HasFactory<MealPlanMealFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meal_type' => MealType::class,
        ];
    }

    /**
     * @return BelongsTo<MealPlan, $this>
     */
    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    /**
     * The food items in this meal, in order.
     *
     * @return HasMany<MealPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MealPlanItem::class)->orderBy('position');
    }

    /**
     * Foods suggested as swaps for the whole meal.
     *
     * @return BelongsToMany<Food, $this>
     */
    public function swaps(): BelongsToMany
    {
        return $this->belongsToMany(Food::class, 'meal_plan_meal_swaps')
            ->withPivot('reason', 'position')
            ->orderByPivot('position');
    }
}
