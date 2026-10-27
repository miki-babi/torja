<?php

use App\Models\User;

test('user:promote promotes a user to admin', function () {
    $user = User::factory()->create();

    $this->artisan('user:promote', ['identifier' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('user:promote promotes a user found by username', function () {
    $user = User::factory()->create(['username' => 'futureadmin']);

    $this->artisan('user:promote', ['identifier' => 'futureadmin'])
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('user:promote promotes a user found by phone', function () {
    $user = User::factory()->create(['phone' => '+251911234567']);

    $this->artisan('user:promote', ['identifier' => '0911 234 567'])
        ->assertSuccessful();

    expect($user->fresh()->isAdmin())->toBeTrue();
});

test('user:promote fails for an unknown identifier', function () {
    $this->artisan('user:promote', ['identifier' => 'nobody@example.com'])
        ->assertFailed();
});
