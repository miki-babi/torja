<?php

namespace App\Support\Tracking;

/**
 * Maps a route name to the area of the app it belongs to, so tracking
 * integrations can be scoped (e.g. only fire the ad pixel on marketing and
 * member pages, never inside the admin panel).
 */
class TrackingScope
{
    /**
     * @var array<string, string>
     */
    public const SCOPES = [
        'marketing',
        'member',
        'shared',
        'auth',
        'settings',
        'admin',
    ];

    /**
     * The scope a route name belongs to, or null for unrouted/other traffic.
     */
    public function for(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        return match (true) {
            $routeName === 'home',
            str_starts_with($routeName, 'calculator'),
            str_starts_with($routeName, 'plan.'),
            str_starts_with($routeName, 'payment.') => 'marketing',

            $routeName === 'dashboard',
            str_starts_with($routeName, 'member.') => 'member',

            str_starts_with($routeName, 'playlists.shared.') => 'shared',

            in_array($routeName, ['login', 'register'], true),
            str_starts_with($routeName, 'password.'),
            str_starts_with($routeName, 'verification.'),
            str_starts_with($routeName, 'two-factor.'),
            str_starts_with($routeName, 'passkeys.'),
            str_starts_with($routeName, 'well-known.') => 'auth',

            str_starts_with($routeName, 'profile.'),
            str_starts_with($routeName, 'security.'),
            str_starts_with($routeName, 'user-password.'),
            str_starts_with($routeName, 'appearance.'),
            str_starts_with($routeName, 'locale.') => 'settings',

            str_starts_with($routeName, 'admin.') => 'admin',

            default => null,
        };
    }
}
