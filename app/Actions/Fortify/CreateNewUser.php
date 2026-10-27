<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['phone'] = PhoneNumber::normalize($input['phone'] ?? null);
        $input['username'] = filled($input['username'] ?? null) ? Str::lower($input['username']) : null;
        $input['email'] = filled($input['email'] ?? null) ? Str::lower($input['email']) : null;

        Validator::make($input, [
            ...$this->profileRules(),
            'email' => [...$this->emailRules(), 'required_without_all:phone,username'],
            'phone' => [...$this->phoneRules(), 'required_without_all:email,username'],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'username' => $input['username'] ?? null,
            'phone' => $input['phone'],
            'email' => $input['email'] ?? null,
            'password' => $input['password'],
        ]);
    }
}
