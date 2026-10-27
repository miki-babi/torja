<?php

use App\Models\TrackingIntegration;
use App\Models\User;

function admin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('admins can create, update and delete integrations', function () {
    $this->actingAs(admin());

    $this->post(route('admin.tracking.store'), [
        'provider' => 'meta',
        'name' => 'Main pixel',
        'external_id' => '123456',
        'scopes' => ['marketing', 'member'],
        'event_map' => ['payment.succeeded' => 'Purchase'],
        'is_enabled' => true,
        'consent_required' => true,
        'config' => ['access_token' => 'capi-secret'],
    ])->assertRedirect();

    $integration = TrackingIntegration::firstOrFail();
    expect($integration->external_id)->toBe('123456')
        ->and($integration->is_enabled)->toBeTrue()
        ->and($integration->accessToken())->toBe('capi-secret')
        ->and($integration->config['access_token'])->not->toBe('capi-secret');

    $this->put(route('admin.tracking.update', $integration), [
        'provider' => 'meta',
        'name' => 'Main pixel',
        'external_id' => '999',
        'scopes' => ['all'],
        'event_map' => [],
        'is_enabled' => false,
        'consent_required' => false,
    ])->assertRedirect();

    expect($integration->fresh()->external_id)->toBe('999');

    $this->delete(route('admin.tracking.destroy', $integration))->assertRedirect();
    expect(TrackingIntegration::count())->toBe(0);
});

test('non-admins cannot manage integrations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.tracking.index'))
        ->assertForbidden();
});

test('enabled integrations reach the shared tracking prop', function () {
    $user = User::factory()->create();
    TrackingIntegration::factory()->create(['scopes' => ['member']]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('tracking.integrations.0.provider', 'meta'));
});

test('disabled or out-of-scope integrations are not shared', function () {
    $user = User::factory()->create();
    TrackingIntegration::factory()->create([
        'is_enabled' => false,
        'scopes' => ['all'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('tracking.integrations', []));
});

test('secrets are never serialized to the frontend', function () {
    $user = User::factory()->create();
    TrackingIntegration::factory()->create([
        'config' => ['access_token' => 'super-secret-token'],
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $page = $response->inertiaProps();
    expect(json_encode($page['tracking']['integrations']))->not->toContain('super-secret-token');
});
