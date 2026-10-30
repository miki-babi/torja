<?php

namespace App\Http\Controllers;

use App\Listeners\BindReferrer;
use App\Models\User;
use App\Support\Tracking\Tracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;

/**
 * The ambassador invite link (/r/{code}). Drops a `tp_ref` cookie so a
 * guest's later registration binds the referrer (last click wins — the
 * cookie is rewritten on every click), binds logged-in users immediately,
 * then sends the visitor somewhere useful: the welcome page for guests,
 * the Pro page for members who haven't upgraded.
 */
class AmbassadorRedirectController extends Controller
{
    /**
     * Invite-cookie lifetime: 30 days.
     */
    private const COOKIE_MINUTES = 60 * 24 * 30;

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $referrer = User::query()->where('referral_code', $code)->firstOrFail();

        app(Tracker::class)->record('ambassador.clicked', $request->user() ?? $referrer, [
            'referrer_id' => $referrer->id,
        ], $referrer);

        $user = $request->user();

        if ($user !== null) {
            app(BindReferrer::class)->bind($user, $code);
        }

        $target = $user === null
            ? route('home').'?'.Arr::query([
                'utm_source' => 'ambassador',
                'utm_medium' => 'invite',
                'utm_content' => $code,
            ])
            : route($user->isPro() ? 'member.home' : 'pro.show');

        if ($user !== null && ! $user->is($referrer)) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Invite applied.'),
            ]);
        }

        return redirect()->to($target)->withCookie(cookie(
            name: BindReferrer::COOKIE,
            value: $code,
            minutes: self::COOKIE_MINUTES,
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
