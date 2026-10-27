<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every web visitor carries a first-party `tp_vid` cookie so guest
 * events can be attributed before — and merged after — registration.
 */
class SetVisitorId
{
    /**
     * The request attribute the Tracker reads the visitor id from.
     */
    public const ATTRIBUTE = 'visitor_id';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->cookie('tp_vid');

        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid();

            $request->cookies->set('tp_vid', $visitorId);
        }

        $request->attributes->set(self::ATTRIBUTE, $visitorId);

        $response = $next($request);

        $response->headers->setCookie(cookie(
            name: 'tp_vid',
            value: $visitorId,
            minutes: 60 * 24 * 365 * 2,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $response;
    }
}
