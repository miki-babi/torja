<?php

namespace App\Http\Requests\Admin;

use App\Models\Food;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
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
            'name_am' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', Rule::in(Food::ALLERGENS)],
            'common_allergy_label' => ['nullable', 'string', 'max:255'],
            'show_in_onboarding' => ['boolean'],
            'is_vegan' => ['boolean'],
        ];
    }
}
