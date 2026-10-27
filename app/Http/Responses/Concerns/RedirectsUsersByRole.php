<?php

namespace App\Http\Responses\Concerns;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Shared post-auth landing: admins go to the admin area, everyone else to
 * the configured home path. Used by the Fortify response classes.
 */
trait RedirectsUsersByRole
{
    private function homeFor(?Authenticatable $user): string
    {
        if ($user instanceof User && $user->isAdmin()) {
            return route('admin.users.index');
        }

        return (string) config('fortify.home', '/dashboard');
    }
}
