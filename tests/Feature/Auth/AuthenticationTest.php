<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('admin users are redirected to the admin area after login', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'login' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.users.index', absolute: false));
});

test('the intended url still wins over the admin redirect', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->withSession(['url.intended' => route('plan.preview', absolute: false)])
        ->post(route('login.store'), [
            'login' => $admin->email,
            'password' => 'password',
        ]);

    $response->assertRedirect(route('plan.preview', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can authenticate with their phone number', function () {
    $user = User::factory()->create(['phone' => '+251911234567']);

    $response = $this->post(route('login.store'), [
        'login' => '0911 234 567',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can authenticate with their username', function () {
    $user = User::factory()->create(['username' => 'fitfan']);

    $response = $this->post(route('login.store'), [
        'login' => 'FITFAN',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can authenticate with their telegram username', function () {
    $user = User::factory()->withTelegram()->create([
        'telegram_username' => 'fitfam',
    ]);

    $response = $this->post(route('login.store'), [
        'login' => '@fitfam',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('passwordless users can not authenticate with a password', function () {
    $user = User::factory()->passwordless()->create(['username' => 'tguser']);

    $response = $this->post(route('login.store'), [
        'login' => 'tguser',
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('login');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
