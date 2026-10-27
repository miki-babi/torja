<?php

namespace App\Models;

use Database\Factories\FoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A food item from the EPHI & FAO Ethiopian Food Composition Table 2025 (EFCT codes)
 * or a meal-plan-only item (ids >= 10001, efct_code null). Values are per 100 g edible
 * portion; carbs = available CHO; fibre = total dietary fibre. `used_in_plans` is a
 * denormalized copy of meal-plan membership kept in sync by FoodDatabaseSeeder.
 *
 * @property int $id
 * @property string|null $efct_code
 * @property string $name_en
 * @property string|null $name_am
 * @property string $category
 * @property string|null $kcal_per_100g
 * @property string|null $protein_g
 * @property string|null $carbs_g
 * @property string|null $fat_g
 * @property string|null $fibre_g
 * @property string|null $image_path
 * @property array<int, string> $allergens
 * @property array<int, string> $meal_types
 * @property bool $is_fasting_friendly
 * @property bool $fish_ok_fasting
 * @property bool $is_vegetarian
 * @property bool $is_vegan
 * @property bool $contains_meat
 * @property bool $goal_fat_loss
 * @property bool $goal_muscle_gain
 * @property string $goal_tag_basis
 * @property array<int, array{plan: string, day: int|null, meal_type: string, raw_text: string}> $used_in_plans
 * @property string $source
 * @property bool $review_needed
 * @property string|null $data_fix_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url
 */
#[Fillable(['efct_code', 'name_en', 'name_am', 'category', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g', 'fibre_g', 'image_path', 'allergens', 'meal_types', 'is_fasting_friendly', 'fish_ok_fasting', 'is_vegetarian', 'is_vegan', 'contains_meat', 'goal_fat_loss', 'goal_muscle_gain', 'goal_tag_basis', 'used_in_plans', 'source', 'review_needed', 'data_fix_note'])]
class Food extends Model
{
    /** @use HasFactory<FoodFactory> */
    use HasFactory;

    /**
     * EU/Codex 14 major allergen codes used across foods and ingredients.
     *
     * @var array<int, string>
     */
    public const ALLERGENS = ['gluten_cereals', 'crustaceans', 'eggs', 'fish', 'peanuts', 'soy', 'milk_dairy', 'tree_nuts', 'celery', 'mustard', 'sesame', 'sulphites', 'lupin', 'molluscs'];

    /**
     * The table associated with the model ('food' is uncountable, so the default would be wrong).
     *
     * @var string
     */
    protected $table = 'foods';

    /**
     * Public URL for the food's image on the public disk, or null.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kcal_per_100g' => 'decimal:1',
            'protein_g' => 'decimal:1',
            'carbs_g' => 'decimal:1',
            'fat_g' => 'decimal:1',
            'fibre_g' => 'decimal:1',
            'allergens' => 'array',
            'meal_types' => 'array',
            'is_fasting_friendly' => 'boolean',
            'fish_ok_fasting' => 'boolean',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'contains_meat' => 'boolean',
            'goal_fat_loss' => 'boolean',
            'goal_muscle_gain' => 'boolean',
            'used_in_plans' => 'array',
            'review_needed' => 'boolean',
        ];
    }

    /**
     * The ingredients that make up this food, in dataset order.
     *
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'food_ingredient')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * Alternative foods suggested as swaps, in dataset order.
     *
     * @return BelongsToMany<Food, $this>
     */
    public function swaps(): BelongsToMany
    {
        return $this->belongsToMany(Food::class, 'food_swaps', 'food_id', 'swap_food_id')
            ->withPivot('reason', 'position')
            ->orderByPivot('position');
    }

    /**
     * Meal-plan line items that reference this food.
     *
     * @return HasMany<MealPlanItem, $this>
     */
    public function mealPlanItems(): HasMany
    {
        return $this->hasMany(MealPlanItem::class);
    }
}
