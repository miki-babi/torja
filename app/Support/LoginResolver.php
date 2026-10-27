<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves the unified `login` identifier (email, phone, username or
 * `@telegram_username`) submitted on the login form to an authenticatable
 * user. Used by `Fortify::authenticateUsing()` — returning null produces the
 * standard "these credentials do not match" failure.
 */
class LoginResolver
{
    /**
     * Find the user matching the request's login identifier and verify the
     * submitted password.
     */
    public function user(Request $request): ?User
    {
        $identifier = Str::lower(trim((string) $request->input('login')));
        $password = (string) $request->input('password');

        if ($identifier === '' || $password === '') {
            return null;
        }

        $user = $this->findByIdentifier($identifier);

        if ($user === null || ! filled($user->password) || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Look the identifier up against every supported login column.
     */
    private function findByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->orWhere('phone', PhoneNumber::normalize($identifier) ?? $identifier)
            ->orWhere('telegram_username', ltrim($identifier, '@'))
            ->first();
    }
}
