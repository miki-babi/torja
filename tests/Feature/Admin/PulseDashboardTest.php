<?php

use App\Models\User;

beforeEach(function () {
    config(['pulse.enabled' => true]);
});

test('admins can view the pulse dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('pulse'))
        ->assertOk();
});

test('non-admin members get a 403 from the pulse dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pulse'))
        ->assertForbidden();
});

test('guests get a 403 from the pulse dashboard', function () {
    $this->get(route('pulse'))->assertForbidden();
});
