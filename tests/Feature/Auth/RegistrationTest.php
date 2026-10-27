<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('new users can register with a phone number instead of an email', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Phone User',
        'phone' => '0911 234 567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::firstWhere('phone', '+251911234567');

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull();
});

test('new users can register with a username instead of an email or phone', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Handle User',
        'username' => 'handleuser',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::firstWhere('username', 'handleuser');

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->phone)->toBeNull();
});

test('registration requires at least one of email, phone or username', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'No Contact',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['email', 'phone']);
    $this->assertGuest();
});

test('phone numbers are normalized before uniqueness checks', function () {
    User::factory()->create(['phone' => '+251911234567']);

    $response = $this->post(route('register.store'), [
        'name' => 'Duplicate Phone',
        'phone' => '0911 234 567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('phone');
});

test('usernames must be unique', function () {
    User::factory()->create(['username' => 'fitfan']);

    $response = $this->post(route('register.store'), [
        'name' => 'Duplicate Username',
        'username' => 'FITFAN',
        'email' => 'other@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
});
