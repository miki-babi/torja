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

test('guests can visit the bmi calculator', function () {
    $this->get(route('calculator.bmi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('calculator/bmi'));
});

test('guests can visit the body fat calculator', function () {
    $this->get(route('calculator.body-fat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('calculator/body-fat'));
});
