<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Passwordless accounts (e.g. Telegram signups) set their first
            // password without supplying a current one.
            'current_password' => filled($this->user()?->password)
                ? $this->currentPasswordRules()
                : ['nullable', 'string'],
            'password' => $this->passwordRules(),
        ];
    }
}
