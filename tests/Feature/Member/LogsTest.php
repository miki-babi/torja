<?php

use App\Models\Food;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\WeightLog;

test('users can log and delete their weight', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.weight-logs.store'), ['weight_kg' => 82.5])
        ->assertCreated();

    $log = $user->weightLogs()->firstOrFail();
    expect((float) $log->weight_kg)->toBe(82.5);

    expect(TrackedEvent::where('name', 'weight.logged')->where('user_id', $user->id)->count())->toBe(1);

    $this->deleteJson(route('member.weight-logs.destroy', $log))->assertOk();
    expect($user->weightLogs()->count())->toBe(0);
});

test('weight log upserts one row per day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.weight-logs.store'), ['weight_kg' => 80]);
    $this->postJson(route('member.weight-logs.store'), ['weight_kg' => 79.5]);

    expect($user->weightLogs()->count())->toBe(1)
        ->and((float) $user->weightLogs()->first()->weight_kg)->toBe(79.5);
});

test('users cannot delete another users weight log', function () {
    $log = WeightLog::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->deleteJson(route('member.weight-logs.destroy', $log))
        ->assertForbidden();
});

test('diary entries can be listed, added and removed', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.diary.store'), [
        'logged_on' => '2026-10-25',
        'name' => 'Enjera',
        'kcal' => 300,
        'source' => 'search',
    ])->assertCreated();

    $entry = $user->foodDiaryEntries()->firstOrFail();

    $this->getJson(route('member.diary.index', ['date' => '2026-10-25']))
        ->assertOk()
        ->assertJsonPath('entries.0.id', $entry->id);

    expect(TrackedEvent::where('name', 'food.logged')->where('user_id', $user->id)->count())->toBe(1);

    $this->deleteJson(route('member.diary.destroy', $entry))->assertOk();
    expect($user->foodDiaryEntries()->count())->toBe(0);
});

test('diary entries can be logged for eaten plan alternatives', function () {
    $user = User::factory()->create();
    $food = Food::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.diary.store'), [
        'logged_on' => '2026-10-26',
        'name' => 'Teff bread',
        'detail' => 'instead of Injera',
        'food_id' => $food->id,
        'source' => 'plan_swap',
    ])->assertCreated();

    $entry = $user->foodDiaryEntries()->sole();
    expect($entry->source)->toBe('plan_swap')
        ->and($entry->food_id)->toBe($food->id)
        ->and($entry->detail)->toBe('instead of Injera');

    $this->postJson(route('member.diary.store'), [
        'logged_on' => '2026-10-26',
        'name' => 'Bogus',
        'source' => 'not_a_source',
    ])->assertUnprocessable();
});
