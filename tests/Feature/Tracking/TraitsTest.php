<?php

use App\Jobs\RecomputeUserTraits;
use App\Models\NutritionProfile;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\UserTrait;
use App\Models\WeightLog;
use App\Models\WorkoutSession;
use App\Support\Segments;

test('traits are computed from profile, events and domain data', function () {
    $user = User::factory()->create();
    NutritionProfile::factory()->create([
        'user_id' => $user->id,
        'goal' => ['primary_goal' => 'lose_fat', 'motivations' => ['health']],
        'obstacles' => ['support' => ['coach_checkins'], 'obstacles' => ['no_time']],
        'budget' => ['budget_amount_etb' => 4000],
    ]);

    WorkoutSession::factory()->count(4)->create(['user_id' => $user->id, 'date' => today()]);
    WeightLog::factory()->create(['user_id' => $user->id, 'logged_on' => today(), 'weight_kg' => 80]);
    WeightLog::factory()->create(['user_id' => $user->id, 'logged_on' => today()->subDays(40), 'weight_kg' => 83]);
    TrackedEvent::factory()->count(6)->create(['user_id' => $user->id, 'name' => 'page.view', 'route_name' => 'member.meal']);
    $user->foodDiaryEntries()->create(['logged_on' => today(), 'name' => 'x', 'source' => 'search']);

    RecomputeUserTraits::dispatchSync($user->id);

    $traits = UserTrait::findOrFail($user->id);
    expect($traits->primary_goal)->toBe('lose_fat')
        ->and($traits->plan_slug)->toBe('fat_loss_7day')
        ->and($traits->wants_coach)->toBeTrue()
        ->and($traits->total_sessions)->toBe(4)
        ->and($traits->pageviews_30d)->toBe(6)
        ->and($traits->food_logs_30d)->toBe(1)
        ->and($traits->weight_logs_count)->toBe(2)
        ->and((float) $traits->latest_weight_kg)->toBe(80.0)
        ->and((float) $traits->weight_delta_30d)->toBe(-3.0)
        ->and($traits->feature_views_30d['member'])->toBe(6);
});

test('segments return the expected members', function () {
    $user = User::factory()->create();
    UserTrait::factory()->create(['user_id' => $user->id, 'wants_coach' => true]);

    expect(Segments::query('coach_leads')->pluck('user_id')->all())
        ->toContain($user->id)
        ->and(Segments::query('paid')->count())->toBe(0)
        ->and(Segments::exists('nonsense'))->toBeFalse();
});

test('analytics page renders for admins and segments export', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    UserTrait::factory()->create(['wants_coach' => true]);

    $this->actingAs($admin)
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/analytics'));

    $this->actingAs($admin)
        ->get(route('admin.analytics.segment.csv', 'coach_leads'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=utf-8');
});
