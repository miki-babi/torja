<?php

use App\Models\BodyMeasurement;
use App\Models\User;

test('guests cannot log measurements', function () {
    $this->postJson(route('member.measurements.store'), [
        'waist_cm' => 80,
    ])->assertUnauthorized();
});

test('members can log and upsert measurements for a day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson(route('member.measurements.store'), [
        'waist_cm' => 80,
        'chest_cm' => 100,
    ])->assertCreated();

    $this->postJson(route('member.measurements.store'), [
        'waist_cm' => 79,
    ])->assertCreated();

    $measurement = BodyMeasurement::where('user_id', $user->id)->sole();

    expect($measurement->waist_cm)->toBe('79.0')
        ->and($measurement->chest_cm)->toBeNull();
});

test('a measurement entry needs at least one field', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('member.measurements.store'), [])
        ->assertUnprocessable();
});

test('members can only delete their own measurements', function () {
    $owner = User::factory()->create();
    $own = BodyMeasurement::create([
        'user_id' => $owner->id,
        'logged_on' => '2026-10-20',
        'waist_cm' => 80,
    ]);
    $other = BodyMeasurement::create([
        'user_id' => User::factory()->create()->id,
        'logged_on' => '2026-10-20',
        'waist_cm' => 90,
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('member.measurements.destroy', $other->id))
        ->assertForbidden();

    $this->actingAs($owner)
        ->deleteJson(route('member.measurements.destroy', $own->id))
        ->assertOk();

    expect(BodyMeasurement::find($own->id))->toBeNull();
});
