<?php

namespace App\Models;

use Database\Factories\MealPlanItemOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A member's chosen food for one meal-plan item — their pick from the
 * item's allowed swap list. Null-safe per (user, item).
 *
 * @property int $id
 * @property int $user_id
 * @property int $meal_plan_item_id
 * @property int $food_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'meal_plan_item_id', 'food_id'])]
class MealPlanItemOverride extends Model
{
    /** @use HasFactory<MealPlanItemOverrideFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<MealPlanItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MealPlanItem::class, 'meal_plan_item_id');
    }

    /** @return BelongsTo<Food, $this> */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
