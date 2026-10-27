<?php

use App\Jobs\RecomputeUserTraits;
use App\Jobs\SendNudge;
use App\Models\Exercise;
use App\Models\ExercisePlaylist;
use App\Models\NutritionProfile;
use App\Models\Payment;
use App\Models\ShortLink;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\UserTrait;
use App\Notifications\NudgeNotification;
use App\Support\IntentScorer;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('first-touch attribution is captured into the cookie and event context', function () {
    $this->get('/?utm_source=instagram&utm_medium=bio&utm_campaign=launch&gclid=abc123')
        ->assertOk()
        ->assertCookie('tp_attr');

    $event = TrackedEvent::query()->where('name', 'page.view')->firstOrFail();

    expect($event->context['attribution']['utm_source'])->toBe('instagram')
        ->and($event->context['attribution']['utm_campaign'])->toBe('launch')
        ->and($event->context['attribution']['gclid'])->toBe('abc123')
        ->and($event->context['attribution']['landing_path'])->toBe('/');
});

test('direct visitors still get a first-touch payload', function () {
    $this->get(route('home'))->assertOk()->assertCookie('tp_attr');

    $event = TrackedEvent::query()->where('name', 'page.view')->firstOrFail();

    expect($event->context['attribution'])->toBeArray()
        ->and($event->context['attribution']['landing_path'])->toBe('/')
        ->and($event->context['attribution'])->not->toHaveKey('utm_source');
});

test('first-touch attribution is not overwritten by later visits', function () {
    $first = json_encode([
        'utm_source' => 'instagram',
        'landing_path' => '/',
        'captured_at' => now()->toIso8601String(),
    ]);

    $this->withCookie('tp_attr', $first)
        ->get('/?utm_source=tiktok')
        ->assertOk();

    $event = TrackedEvent::query()->latest('occurred_at')->firstOrFail();

    expect($event->context['attribution']['utm_source'])->toBe('instagram');
});

test('shared playlist links carry ref and utm params', function () {
    $playlist = ExercisePlaylist::factory()->create([
        'share_token' => (string) Str::uuid(),
    ]);

    $url = $playlist->shareUrl();

    // The short link redirects to the full URL carrying attribution params.
    $destination = ShortLink::sole()->url;

    expect($destination)->toContain('ref='.$playlist->user_id)
        ->toContain('utm_source=member')
        ->toContain('utm_medium=share');
});

test('registration stamps first-touch attribution on the user', function () {
    $attribution = [
        'utm_source' => 'tiktok',
        'landing_path' => '/calculator',
        'captured_at' => now()->toIso8601String(),
    ];

    $this->withCookie('tp_attr', json_encode($attribution))
        ->post(route('register'), [
            'name' => 'Miki Tesfaye',
            'email' => 'miki@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $user = User::query()->where('email', 'miki@example.com')->firstOrFail();

    expect($user->attribution['utm_source'])->toBe('tiktok');
});

test('the goal wizard step records primary_goal on the step event', function () {
    $this->withCookie(config('session.cookie'), 'growthsessionidxxxxxxxxxxxxxxxxxxxxxxxx');

    $this->patch(route('plan.wizard.update', 'goal'), [
        'primary_goal' => 'build_muscle',
        'motivations' => ['health'],
        'priorities' => ['high_protein'],
    ])->assertSessionHasNoErrors();

    $event = TrackedEvent::query()
        ->where('name', 'wizard.step_completed')
        ->latest('occurred_at')
        ->firstOrFail();

    expect($event->properties['section'])->toBe('goal')
        ->and($event->properties['primary_goal'])->toBe('build_muscle');
});

test('declared goal wins; behavior predicts when undeclared', function () {
    $declared = User::factory()->create();
    NutritionProfile::factory()->create([
        'user_id' => $declared->id,
        'goal' => ['primary_goal' => 'lose_fat'],
    ]);

    expect(app(IntentScorer::class)->forUser($declared))
        ->toMatchArray(['goal' => 'lose_fat', 'source' => IntentScorer::DECLARED]);

    $predicted = User::factory()->create();
    Exercise::factory()->count(3)->create([
        'goal_muscle_gain' => true,
        'goal_fat_loss' => false,
    ])->each(function (Exercise $exercise) use ($predicted): void {
        TrackedEvent::factory()->create([
            'user_id' => $predicted->id,
            'name' => 'exercise.viewed',
            'subject_type' => $exercise->getMorphClass(),
            'subject_id' => $exercise->slug,
        ]);
    });

    expect(app(IntentScorer::class)->forUser($predicted))
        ->toMatchArray(['goal' => 'build_muscle', 'source' => IntentScorer::PREDICTED]);
});

test('traits carry channel, wizard state and goal source', function () {
    $user = User::factory()->create([
        'attribution' => [
            'utm_source' => 'instagram',
            'referrer' => 'https://instagram.com/p/abc',
        ],
    ]);
    NutritionProfile::factory()->create([
        'user_id' => $user->id,
        'status' => 'in_progress',
        'current_step' => 6,
    ]);

    RecomputeUserTraits::dispatchSync($user->id);

    $traits = UserTrait::findOrFail($user->id);

    expect($traits->channel)->toBe('instagram')
        ->and($traits->utm_source)->toBe('instagram')
        ->and($traits->referrer_host)->toBe('instagram.com')
        ->and($traits->wizard_status)->toBe('in_progress')
        ->and($traits->wizard_step)->toBe(6);
});

test('the in-app nudge surfaces for stalled users and hides once paid', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->create([
        'user_id' => $user->id,
        'status' => 'in_progress',
        'current_step' => 5,
        'goal' => ['primary_goal' => 'lose_fat'],
    ]);

    $this->actingAs($user)->get(route('member.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('nudge.slug', 'wizard_abandoned')
            ->where('nudge.url', route('plan.wizard')));

    Payment::factory()->successful()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('member.home'))
        ->assertInertia(fn (Assert $page) => $page->where('nudge', null));
});

test('dismissed nudges stay hidden inside their cooldown', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->create([
        'user_id' => $user->id,
        'status' => 'in_progress',
        'current_step' => 5,
    ]);

    TrackedEvent::factory()->create([
        'user_id' => $user->id,
        'name' => 'nudge.dismissed',
        'properties' => ['nudge' => 'wizard_abandoned'],
    ]);

    $this->actingAs($user)->get(route('member.home'))
        ->assertInertia(fn (Assert $page) => $page->where('nudge', null));
});

test('nudge jobs notify once per cooldown and log the send', function () {
    Notification::fake();

    $user = User::factory()->create();
    NutritionProfile::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
        'goal' => ['primary_goal' => 'lose_fat'],
    ]);

    SendNudge::dispatchSync($user->id, 'plan_ready_unpaid');

    Notification::assertSentTo($user, NudgeNotification::class);
    expect(TrackedEvent::query()
        ->where('user_id', $user->id)
        ->where('name', 'nudge.sent')
        ->where('properties->nudge', 'plan_ready_unpaid')
        ->count())->toBe(1);

    SendNudge::dispatchSync($user->id, 'plan_ready_unpaid');

    Notification::assertSentToTimes($user, NudgeNotification::class, 1);
});

test('nudges:send queues sends for matching segment members', function () {
    Queue::fake();

    $user = User::factory()->create();
    UserTrait::factory()->create([
        'user_id' => $user->id,
        'wizard_status' => 'completed',
        'paid' => false,
    ]);

    $this->artisan('nudges:send')->assertSuccessful();

    Queue::assertPushed(SendNudge::class, fn (SendNudge $job): bool => $job->userId === $user->id
        && $job->slug === 'plan_ready_unpaid');
});

test('the traffic funnel groups visitors and payers by channel', function () {
    $admin = User::factory()->admin()->create();

    TrackedEvent::factory()->create([
        'visitor_id' => (string) Str::uuid(),
        'context' => [
            'attribution' => [
                'utm_source' => 'instagram',
                'landing_path' => '/',
                'captured_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $payer = User::factory()->create([
        'attribution' => ['utm_source' => 'instagram'],
    ]);
    UserTrait::factory()->create([
        'user_id' => $payer->id,
        'channel' => 'instagram',
        'paid' => true,
        'primary_goal' => 'lose_fat',
    ]);

    $this->actingAs($admin)->get(route('admin.analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('traffic', function (mixed $rows): bool {
                $row = collect($rows)->firstWhere('channel', 'instagram');

                return $row !== null
                    && $row['visitors'] === 1
                    && $row['registered'] === 1
                    && $row['paid'] === 1
                    && $row['goals'] === ['lose_fat' => 1];
            }));
});
