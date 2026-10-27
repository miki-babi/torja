<?php

namespace App\Http\Requests\Admin;

/**
 * Creating an ingredient uses the same payload as editing one — the slug is
 * derived from the English name server-side.
 */
class StoreIngredientRequest extends UpdateIngredientRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'added_for' => ['nullable', 'string', 'max:255'],
        ];
    }
}
