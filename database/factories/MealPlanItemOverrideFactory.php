<?php

namespace Database\Factories;

use App\Models\Food;
use App\Models\MealPlanItem;
use App\Models\MealPlanItemOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealPlanItemOverride>
 */
class MealPlanItemOverrideFactory extends Factory
{
    /** @var class-string<MealPlanItemOverride> */
    protected $model = MealPlanItemOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'meal_plan_item_id' => MealPlanItem::factory(),
            'food_id' => Food::factory(),
        ];
    }
}
