<?php

namespace App\Http\Middleware;

use App\Support\Tracking\Tracker;
use Closure;
use Illuminate\Http\Request;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a `page.view` event for every successful GET page visit — browser
 * document loads and Inertia navigations alike. Prefetches are excluded so
 * hover-prefetching doesn't inflate the numbers; partial reloads (deferred
 * props, polling) are recorded with their own visit kind so analytics can
 * exclude them when measuring navigations.
 */
class TrackPageView
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTrack($request, $response)) {
            return $response;
        }

        app(Tracker::class)->record('page.view', properties: [
            'visit_kind' => $this->visitKind($request),
        ]);

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->isSuccessful()
            && $request->route() !== null
            && ! $request->prefetch();
    }

    private function visitKind(Request $request): string
    {
        if ($request->header(Header::INERTIA) !== 'true') {
            return 'document';
        }

        return $request->header(Header::PARTIAL_COMPONENT) !== null
            ? 'partial'
            : 'visit';
    }
}
