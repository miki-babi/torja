<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\TrackingIntegration;
use App\Support\Locales;
use App\Support\Nudges\NudgeResolver;
use App\Support\Translator;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'pro' => $request->user()?->isPro() ?? false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => app()->getLocale(),
            'languages' => Locales::active()
                ->map(fn (Language $l): array => [
                    'code' => $l->code,
                    'name' => $l->name,
                    'native_name' => $l->native_name,
                ])
                ->all(),
            'translations' => Translator::map(app()->getLocale()),
            'nudge' => app(NudgeResolver::class)->forRequest($request),
            'tracking' => [
                'visitor_id' => $request->attributes->get('visitor_id'),
                'integrations' => TrackingIntegration::frontendPayload(
                    $request->route()?->getName(),
                ),
            ],
        ];
    }
}
