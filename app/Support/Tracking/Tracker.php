<?php

namespace App\Support\Tracking;

use App\Http\Middleware\CaptureAttribution;
use App\Models\TrackedEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records product interactions into `tracked_events`. HTTP-context writes are
 * deferred until after the response is sent so tracking never adds latency to
 * a request; console/test contexts write synchronously. Recording failures are
 * reported and swallowed — analytics must never break the request.
 */
class Tracker
{
    /**
     * Record an event for the current request context.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $name,
        ?User $user = null,
        array $properties = [],
        ?Model $subject = null,
    ): void {
        try {
            if (app()->runningInConsole()) {
                $this->write($name, $user, $properties, $subject);

                return;
            }

            defer(fn () => $this->write($name, $user, $properties, $subject));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Persist the event row with the current request's attribution context.
     *
     * @param  array<string, mixed>  $properties
     */
    private function write(
        string $name,
        ?User $user,
        array $properties,
        ?Model $subject,
    ): void {
        $request = app('request') instanceof Request ? app('request') : null;
        $user ??= $request?->user();

        TrackedEvent::create([
            'event_uid' => (string) Str::uuid(),
            'visitor_id' => $request?->attributes->get('visitor_id') ?? $request?->cookie('tp_vid'),
            'user_id' => $user?->id,
            'session_id' => $this->sessionId($request),
            'name' => $name,
            'route_name' => $request?->route()?->getName(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'context' => $request === null ? null : $this->context($request),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array{path: string, route_name: string|null, referrer: string|null, attribution: array<string, mixed>|null, locale: string, device: string, ip_hash: string|null}
     */
    private function context(Request $request): array
    {
        return [
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => $request->route()?->getName(),
            'referrer' => $request->headers->get('referer'),
            'attribution' => $request->attributes->get(CaptureAttribution::ATTRIBUTE),
            'locale' => app()->getLocale(),
            'device' => $this->device($request->userAgent() ?? ''),
            'ip_hash' => $request->ip() === null
                ? null
                : substr(hash('sha256', $request->ip().config('app.key')), 0, 16),
        ];
    }

    private function sessionId(?Request $request): ?string
    {
        try {
            return $request?->hasSession() ? $request->session()->getId() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function device(string $agent): string
    {
        if ($agent === '') {
            return 'unknown';
        }

        if (preg_match('/bot|crawler|spider|slurp|pingdom/i', $agent) === 1) {
            return 'bot';
        }

        if (preg_match('/tablet|ipad/i', $agent) === 1) {
            return 'tablet';
        }

        if (preg_match('/mobile|android|iphone|ipod|phone/i', $agent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }
}
