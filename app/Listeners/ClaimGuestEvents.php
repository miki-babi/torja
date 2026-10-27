<?php

namespace App\Listeners;

use App\Models\TrackedEvent;
use App\Models\User;
use App\Support\Tracking\Tracker;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

/**
 * Attributes a guest's tracked events to their account once they log in or
 * register, so pre-signup activity (wizard, browsing) joins the user history.
 */
class ClaimGuestEvents
{
    /**
     * Merge the current visitor's guest events into the user's history and
     * record the auth event itself.
     */
    public function handle(Login|Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        app(Tracker::class)->record(
            $event instanceof Registered ? 'auth.registered' : 'auth.logged_in',
            $user,
        );

        $request = app('request');

        if (! $request instanceof Request) {
            return;
        }

        $visitorId = $request->attributes->get('visitor_id') ?? $request->cookie('tp_vid');
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        $attribution = $request->attributes->get('attribution')
            ?? $this->decodeAttributionCookie($request);

        defer(function () use ($user, $visitorId, $sessionId, $attribution): void {
            TrackedEvent::query()
                ->whereNull('user_id')
                ->where(function ($query) use ($visitorId, $sessionId): void {
                    if (is_string($visitorId) && $visitorId !== '') {
                        $query->where('visitor_id', $visitorId);
                    }

                    if (is_string($sessionId) && $sessionId !== '') {
                        $query->orWhere('session_id', $sessionId);
                    }
                })
                ->update(['user_id' => $user->id]);

            if (is_array($attribution) && $attribution !== [] && $user->attribution === null) {
                $user->forceFill(['attribution' => $attribution])->save();
            }
        });
    }

    /**
     * Decode the first-touch attribution cookie when the request attribute
     * was not populated (cookie set on an earlier visit).
     *
     * @return array<string, mixed>|null
     */
    private function decodeAttributionCookie(Request $request): ?array
    {
        $json = $request->cookie('tp_attr');

        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }
}
