<?php

namespace App\Models;

use App\Enums\MealType;
use Database\Factories\MealProgramMealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One meal slot inside a day of a MealProgram (e.g. day 2 lunch). On claim
 * it becomes a MealPlanMeal in the member's meal-plan copy.
 *
 * @property int $id
 * @property int $meal_program_id
 * @property int $day
 * @property MealType $meal_type
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['meal_program_id', 'day', 'meal_type', 'position'])]
class MealProgramMeal extends Model
{
    /** @use HasFactory<MealProgramMealFactory> */
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
     * @return BelongsTo<MealProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(MealProgram::class, 'meal_program_id');
    }

    /**
     * The meal's food items, in display order.
     *
     * @return HasMany<MealProgramItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MealProgramItem::class, 'meal_program_meal_id')
            ->orderBy('position');
    }
}
