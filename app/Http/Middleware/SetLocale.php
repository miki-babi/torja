<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the request locale: member preference, then the locale cookie
 * (guests), then the default language. Invalid values fall through.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if (! is_string($locale) || ! Locales::isActive($locale)) {
            $cookie = $request->cookie('locale');
            $locale = is_string($cookie) ? $cookie : null;
        }

        $locale = Locales::isActive($locale) ? $locale : Locales::defaultCode();

        app()->setLocale($locale);

        return $next($request);
    }
}
