<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First-touch marketing attribution. On the visitor's first request the
 * `tp_attr` cookie is seeded with the campaign params on the URL (utm_*,
 * click ids, `ref` share links) plus referrer/landing path — or with just
 * landing path + referrer when no params are present, so "direct" is a real
 * first-touch state distinct from unattributed legacy traffic. The cookie
 * is never overwritten: first touch wins. The resolved payload is exposed
 * as the `attribution` request attribute — read by the Tracker into every
 * event's context and stamped onto `users.attribution` at registration.
 */
class CaptureAttribution
{
    public const ATTRIBUTE = 'attribution';

    public const COOKIE = 'tp_attr';

    /**
     * Query-string keys captured as attribution.
     *
     * @var array<int, string>
     */
    private const KEYS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'ttclid',
        'ref',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $existing = $this->decode($request->cookie(self::COOKIE));

        $request->attributes->set(self::ATTRIBUTE, $existing ?? $this->fromRequest($request));

        $response = $next($request);

        if ($existing === null) {
            $response->headers->setCookie(cookie(
                name: self::COOKIE,
                value: json_encode($request->attributes->get(self::ATTRIBUTE)),
                minutes: 60 * 24 * 365 * 2,
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $response;
    }

    /**
     * Build the first-touch payload from this request: campaign params from
     * the query string (if any) plus referrer and landing path.
     *
     * @return array<string, mixed>
     */
    private function fromRequest(Request $request): array
    {
        $payload = [];

        foreach (self::KEYS as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $payload[$key] = mb_substr($value, 0, 120);
            }
        }

        $referrer = $request->headers->get('referer');

        if (is_string($referrer) && $referrer !== '') {
            $payload['referrer'] = mb_substr($referrer, 0, 500);
        }

        $payload['landing_path'] = '/'.ltrim($request->path(), '/');
        $payload['captured_at'] = now()->toIso8601String();

        return $payload;
    }

    /**
     * Decode the stored attribution cookie, or null when absent/invalid.
     *
     * @return array<string, mixed>|null
     */
    private function decode(?string $json): ?array
    {
        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }
}
