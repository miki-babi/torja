<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Referrals\ProGrantEvaluator;
use App\Support\Tracking\Tracker;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

/**
 * Binds the ambassador behind the `tp_ref` invite cookie to an account
 * when it registers or logs in. Last click wins: a newer invite link
 * always replaces an older referrer. Self-referrals are ignored.
 */
class BindReferrer
{
    /**
     * The cookie written by the /r/{code} invite redirect.
     */
    public const COOKIE = 'tp_ref';

    /**
     * Resolve the cookie to a user and stamp `referred_by_id`.
     */
    public function handle(Login|Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $request = app('request');

        if (! $request instanceof Request) {
            return;
        }

        $this->bind($user, $request->cookie(self::COOKIE));
    }

    /**
     * Bind `referred_by_id` from an invite code — shared by this listener
     * (guests converting) and the invite redirect (logged-in clicks).
     */
    public function bind(User $user, ?string $code): void
    {
        if (! is_string($code) || $code === '') {
            return;
        }

        $referrer = User::query()->where('referral_code', $code)->first();

        if ($referrer === null || $referrer->is($user) || $user->referred_by_id === $referrer->id) {
            return;
        }

        $user->forceFill(['referred_by_id' => $referrer->id])->save();

        app(Tracker::class)->record('ambassador.bound', $user, [
            'referrer_id' => $referrer->id,
        ]);

        app(ProGrantEvaluator::class)->evaluate($referrer);
    }
}
