<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can visit the calculator', function () {
    $this->get(route('calculator'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('calculator'));
});

test('authenticated users can visit the calculator', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('calculator'));
    $response->assertOk();
});
