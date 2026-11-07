<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The member app is the client-facing surface of a coach tenant — members
 * must be linked to at least one trainer to use it. Staff (admins and
 * trainers, who DM clients through member routes) bypass the gate, and
 * members with no coach land on a "find your coach" screen. Payment
 * callbacks stay exempt so a return from checkout can't be stranded.
 */
class EnsureMemberHasCoach
{
    /**
     * Routes reachable without a coach: the gate screen itself, the role
     * dispatcher, and payment completion endpoints.
     *
     * @var list<string>
     */
    private const EXEMPT = [
        'member.no-coach',
        'dashboard',
        'payment.callback',
        'payment.status',
        'payment.verify',
        'payment.cancel',
    ];

    /**
     * Redirect coachless members to the gate screen.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || $user->isAdmin()
            || $user->isTrainer()
            || $request->routeIs(self::EXEMPT)
            || $user->activeCoach() !== null) {
            return $next($request);
        }

        if (! $request->isMethod('GET') || $request->expectsJson()) {
            abort(403);
        }

        return to_route('member.no-coach');
    }
}
