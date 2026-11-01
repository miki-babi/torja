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
        'config' => [
            'access_token' => 'capi-secret',
            'snippet' => '<script>fbq("init", "123456")</script>',
        ],
    ])->assertRedirect();

    $integration = TrackingIntegration::firstOrFail();
    expect($integration->external_id)->toBe('123456')
        ->and($integration->is_enabled)->toBeTrue()
        ->and($integration->accessToken())->toBe('capi-secret')
        ->and($integration->config['access_token'])->not->toBe('capi-secret')
        ->and($integration->config['snippet'])->toBe('<script>fbq("init", "123456")</script>');

    $this->put(route('admin.tracking.update', $integration), [
        'provider' => 'meta',
        'name' => 'Main pixel',
        'external_id' => '999',
        'scopes' => ['all'],
        'event_map' => [],
        'is_enabled' => false,
        'consent_required' => false,
    ])->assertRedirect();

    expect($integration->fresh()->external_id)->toBe('999')
        ->and($integration->accessToken())->toBe('capi-secret')
        ->and($integration->config['snippet'])->toBe('<script>fbq("init", "123456")</script>');

    $this->put(route('admin.tracking.update', $integration), [
        'provider' => 'meta',
        'name' => 'Main pixel',
        'external_id' => '999',
        'scopes' => ['all'],
        'event_map' => [],
        'is_enabled' => false,
        'consent_required' => false,
        'config' => ['access_token' => '', 'snippet' => ''],
    ])->assertRedirect();

    expect($integration->fresh()->accessToken())->toBe('capi-secret')
        ->and($integration->fresh()->config['snippet'] ?? null)->toBeNull();

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
    TrackingIntegration::factory()->create([
        'scopes' => ['member'],
        'config' => ['snippet' => '<script>console.log("pixel")</script>'],
    ]);

    $response = $this->actingAs($user)->get(route('member.home'));

    $response->assertInertia(fn ($page) => $page
        ->where('tracking.integrations.0.provider', 'meta')
        ->where('tracking.integrations.0.snippet', '<script>console.log("pixel")</script>'));
});

test('disabled or out-of-scope integrations are not shared', function () {
    $user = User::factory()->create();
    TrackingIntegration::factory()->create([
        'is_enabled' => false,
        'scopes' => ['all'],
    ]);

    $this->actingAs($user)
        ->get(route('member.home'))
        ->assertInertia(fn ($page) => $page->where('tracking.integrations', []));
});

test('secrets are never serialized to the frontend', function () {
    $user = User::factory()->create();
    TrackingIntegration::factory()->create([
        'config' => [
            'access_token' => 'super-secret-token',
            'snippet' => '<script>window.pixel = 1</script>',
        ],
    ]);

    $response = $this->actingAs($user)->get(route('member.home'));

    $page = $response->inertiaProps();
    $payload = json_encode($page['tracking']['integrations']);
    expect($payload)->not->toContain('super-secret-token')
        ->toContain('window.pixel = 1');
});
