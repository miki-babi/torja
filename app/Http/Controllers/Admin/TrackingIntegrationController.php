<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackingIntegration;
use App\Support\Tracking\TrackingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrackingIntegrationController extends Controller
{
    /**
     * List all tracking integrations with the available scopes and the
     * internal event catalog admins can map to provider events.
     */
    public function index(): Response
    {
        return Inertia::render('admin/tracking/index', [
            'integrations' => TrackingIntegration::query()
                ->orderBy('provider')
                ->orderBy('name')
                ->get()
                ->map(fn (TrackingIntegration $i): array => [
                    'id' => $i->id,
                    'provider' => $i->provider,
                    'name' => $i->name,
                    'external_id' => $i->external_id,
                    'scopes' => $i->scopes,
                    'event_map' => $i->event_map ?? [],
                    'is_enabled' => $i->is_enabled,
                    'consent_required' => $i->consent_required,
                    'has_secret' => ! empty($i->config['access_token']),
                ]),
            'scopeOptions' => TrackingScope::SCOPES,
            'eventOptions' => $this->eventCatalog(),
        ]);
    }

    /**
     * Create a tracking integration.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $accessToken = $data['config']['access_token'] ?? null;
        unset($data['config']);

        $integration = TrackingIntegration::create($data);

        if (is_string($accessToken) && $accessToken !== '') {
            $integration->setAccessToken($accessToken);
        }

        return back();
    }

    /**
     * Update a tracking integration. Secret fields are only replaced when a
     * new value is submitted.
     */
    public function update(Request $request, TrackingIntegration $trackingIntegration): RedirectResponse
    {
        $data = $this->validated($request);

        $accessToken = $data['config']['access_token'] ?? null;
        unset($data['config']);

        $trackingIntegration->update($data);

        if (is_string($accessToken) && $accessToken !== '') {
            $trackingIntegration->setAccessToken($accessToken);
        }

        return back();
    }

    /**
     * Delete a tracking integration.
     */
    public function destroy(TrackingIntegration $trackingIntegration): RedirectResponse
    {
        $trackingIntegration->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'provider' => ['required', 'string', Rule::in(['meta', 'tiktok', 'google', 'custom'])],
            'name' => ['required', 'string', 'max:80'],
            'external_id' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in([...TrackingScope::SCOPES, 'all'])],
            'event_map' => ['nullable', 'array'],
            'event_map.*' => ['string', 'max:80'],
            'is_enabled' => ['required', 'boolean'],
            'consent_required' => ['required', 'boolean'],
            'config.access_token' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * The internal events admins can map to provider event names.
     *
     * @return array<int, string>
     */
    private function eventCatalog(): array
    {
        return [
            'auth.registered',
            'auth.logged_in',
            'wizard.step_completed',
            'wizard.completed',
            'checkout.started',
            'payment.succeeded',
            'session.started',
            'workout.set_logged',
            'playlist.created',
            'playlist.shared',
            'shared_playlist.viewed',
            'shared_playlist.saved',
            'meal.viewed',
            'exercise.viewed',
            'food.logged',
            'weight.logged',
            'habit.toggled',
        ];
    }
}
