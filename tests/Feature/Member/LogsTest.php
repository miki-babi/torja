<?php

use App\Models\User;
use App\Models\WeightLog;

test('users can log and delete their weight', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.weight-logs.store'), ['weight_kg' => 82.5])
        ->assertCreated();

    $log = $user->weightLogs()->firstOrFail();
    expect((float) $log->weight_kg)->toBe(82.5);

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
