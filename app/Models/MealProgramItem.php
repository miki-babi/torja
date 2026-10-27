<?php

namespace App\Models;

use Database\Factories\MealProgramItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One food entry inside a MealProgramMeal, matched to a Food record.
 *
 * @property int $id
 * @property int $meal_program_meal_id
 * @property int $food_id
 * @property string|null $portion_note
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['meal_program_meal_id', 'food_id', 'portion_note', 'position'])]
class MealProgramItem extends Model
{
    /** @use HasFactory<MealProgramItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<MealProgramMeal, $this>
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(MealProgramMeal::class, 'meal_program_meal_id');
    }

    /**
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
