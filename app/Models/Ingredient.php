<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A canonical ingredient from the app's ingredients list.
 *
 * @property int $id
 * @property string $slug
 * @property string $name_en
 * @property string $name_am
 * @property string $category
 * @property array<int, string> $allergens
 * @property string|null $common_allergy_label
 * @property bool $show_in_onboarding
 * @property bool $is_vegan
 * @property int $used_by_foods
 * @property string|null $added_for
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name_en', 'name_am', 'category', 'allergens', 'common_allergy_label', 'show_in_onboarding', 'is_vegan', 'used_by_foods', 'added_for'])]
class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allergens' => 'array',
            'show_in_onboarding' => 'boolean',
            'is_vegan' => 'boolean',
        ];
    }

    /**
     * The foods that contain this ingredient, in dataset order.
     *
     * @return BelongsToMany<Food, $this>
     */
    public function foods(): BelongsToMany
    {
        return $this->belongsToMany(Food::class, 'food_ingredient')
            ->withPivot('position');
    }
}
