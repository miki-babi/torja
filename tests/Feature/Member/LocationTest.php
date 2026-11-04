<?php

use App\Models\Gym;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members share their coordinates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('member.location.store'), [
            'latitude' => 9.0301,
            'longitude' => 38.7654,
        ])
        ->assertOk();

    expect($user->fresh()->latitude)->toBe(9.0301)
        ->and($user->longitude)->toBe(38.7654)
        ->and($user->location_shared_at)->not->toBeNull();
});

test('out of range coordinates are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('member.location.store'), [
            'latitude' => 91,
            'longitude' => 38.7654,
        ])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->postJson(route('member.location.store'), [
            'latitude' => 9.0301,
            'longitude' => 181,
        ])
        ->assertUnprocessable();
});

test('nearby members are suggested with their distance as the reason', function () {
    $me = User::factory()->create([
        'leaderboard_opted_in' => true,
        'latitude' => 9.0301,
        'longitude' => 38.7654,
    ]);
    // ~2 km away — inside the ≤10 km tier.
    $near = User::factory()->create([
        'latitude' => 9.0481,
        'longitude' => 38.7654,
    ]);
    // ~300 km away — outside every proximity tier.
    User::factory()->create([
        'latitude' => 12.0301,
        'longitude' => 38.7654,
    ]);

    $this->actingAs($me)
        ->get(route('member.messages'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasLocation', true)
            ->where('suggestions.0.id', $near->id)
            ->where('suggestions.0.reason.key', 'nearby'));
});

test('gym search annotates distances and orders nearest first', function () {
    $me = User::factory()->create([
        'latitude' => 9.0301,
        'longitude' => 38.7654,
    ]);
    // ~110 km away.
    $far = Gym::create(['name' => 'Far Gym', 'latitude' => 10.0301, 'longitude' => 38.7654]);
    // ~2 km away.
    $near = Gym::create([
        'name' => 'Near Gym',
        'latitude' => 9.0481,
        'longitude' => 38.7654,
    ]);
    Gym::create(['name' => 'No Pin Gym']);

    $response = $this->actingAs($me)
        ->getJson(route('member.ranks.gyms.index'))
        ->assertOk()
        ->assertJsonPath('gyms.0.id', $near->id)
        ->assertJsonPath('gyms.1.id', $far->id);

    $nearDistance = $response->json('gyms.0.distance_km');
    expect($nearDistance)->toBeGreaterThan(1)->toBeLessThan(3);

    $farDistance = $response->json('gyms.1.distance_km');
    expect($farDistance)->toBeGreaterThan(100);
});

test('a gym created by a member who shared their location gets pinned', function () {
    $me = User::factory()->create([
        'latitude' => 9.0301,
        'longitude' => 38.7654,
    ]);

    $this->actingAs($me)
        ->postJson(route('member.ranks.gym.store'), ['name' => 'Pin Gym'])
        ->assertOk();

    $gym = Gym::sole();
    expect($gym->latitude)->toBe(9.0301)
        ->and($gym->longitude)->toBe(38.7654);
});

test('a gym created without a shared location stays unpinned', function () {
    $me = User::factory()->create();

    $this->actingAs($me)
        ->postJson(route('member.ranks.gym.store'), ['name' => 'Free Gym'])
        ->assertOk();

    $gym = Gym::sole();
    expect($gym->latitude)->toBeNull()
        ->and($gym->longitude)->toBeNull();
});
