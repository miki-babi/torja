<?php

use App\Models\TrackedEvent;
use App\Models\User;
use Illuminate\Support\Str;

test('a page view event is recorded for document loads', function () {
    $this->get(route('home'))->assertOk();

    $event = TrackedEvent::query()->where('name', 'page.view')->firstOrFail();

    expect($event->route_name)->toBe('home')
        ->and($event->visitor_id)->not->toBeNull()
        ->and($event->properties['visit_kind'])->toBe('document');
});

test('the visitor cookie is issued and reused', function () {
    $first = $this->get(route('home'));
    $first->assertCookie('tp_vid');

    $visitorId = (string) Str::uuid();

    $this->withCookie('tp_vid', $visitorId)->get(route('home'));
    $this->withCookie('tp_vid', $visitorId)->get(route('calculator'));

    expect(
        TrackedEvent::query()->where('visitor_id', $visitorId)->count(),
    )->toBe(2);
});

test('client beacon events are stored', function () {
    $this->postJson('/t', [
        'events' => [
            ['name' => 'share_link.copied', 'properties' => ['playlist_id' => 3]],
            ['name' => 'water.logged', 'properties' => ['ml_added' => 250]],
        ],
    ])->assertOk();

    expect(TrackedEvent::query()->where('name', 'share_link.copied')->count())->toBe(1)
        ->and(TrackedEvent::query()->where('name', 'water.logged')->count())->toBe(1);
});

test('beacon rejects invalid payloads', function () {
    $this->postJson('/t', ['events' => [['name' => 'BAD NAME!']]])
        ->assertUnprocessable();

    $this->postJson('/t', [])->assertUnprocessable();
});

test('guest events are claimed by the user on login', function () {
    $visitorId = (string) Str::uuid();

    $this->withCredentials()
        ->withCookie('tp_vid', $visitorId)
        ->postJson('/t', ['events' => [['name' => 'water.logged']]])
        ->assertOk();

    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->withCookie('tp_vid', $visitorId)
        ->post(route('login'), [
            'login' => $user->email,
            'password' => 'password',
        ]);

    expect(TrackedEvent::query()->where('name', 'water.logged')->value('user_id'))
        ->toBe($user->id);

    expect(TrackedEvent::query()->where('name', 'auth.logged_in')->count())->toBe(1);
});

test('page views attribute the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('member.home'))->assertOk();

    expect(
        TrackedEvent::query()
            ->where('name', 'page.view')
            ->where('user_id', $user->id)
            ->count(),
    )->toBeGreaterThanOrEqual(1);
});
