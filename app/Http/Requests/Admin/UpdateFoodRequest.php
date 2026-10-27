<?php

namespace App\Http\Requests\Admin;

use App\Enums\MealType;
use App\Models\Food;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFoodRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255'],
            'name_am' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'kcal_per_100g' => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'carbs_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
            'fibre_g' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'meal_types' => ['nullable', 'array'],
            'meal_types.*' => ['string', Rule::in(array_column(MealType::cases(), 'value'))],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', Rule::in(Food::ALLERGENS)],
            'is_fasting_friendly' => ['boolean'],
            'fish_ok_fasting' => ['boolean'],
            'is_vegetarian' => ['boolean'],
            'is_vegan' => ['boolean'],
            'contains_meat' => ['boolean'],
            'goal_fat_loss' => ['boolean'],
            'goal_muscle_gain' => ['boolean'],
            'ingredient_ids' => ['nullable', 'array'],
            'ingredient_ids.*' => ['integer', Rule::exists('ingredients', 'id')],
            'review_needed' => ['boolean'],
            'data_fix_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
