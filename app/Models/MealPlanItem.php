<?php

namespace App\Models;

use App\Enums\MatchConfidence;
use Database\Factories\MealPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One food line inside a meal-plan meal, matched to a Food record.
 *
 * @property int $id
 * @property int $meal_plan_meal_id
 * @property int $food_id
 * @property string $raw_text
 * @property string|null $portion_note
 * @property MatchConfidence $match_confidence
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['meal_plan_meal_id', 'food_id', 'raw_text', 'portion_note', 'match_confidence', 'position'])]
class MealPlanItem extends Model
{
    /** @use HasFactory<MealPlanItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_confidence' => MatchConfidence::class,
        ];
    }

    /**
     * @return BelongsTo<MealPlanMeal, $this>
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(MealPlanMeal::class, 'meal_plan_meal_id');
    }

    /**
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
