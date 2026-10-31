<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login from the admin area', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('non-admin users get a 403 from the admin area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('unverified admins are redirected to verification', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('admins can view the dashboard with stats and recent payments', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.total_users', 4)
            ->where('stats.paid_users', 0)
            ->has('recentPayments'));
});

test('all admin routes reject non-admin users', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with([
    'admin.users.index',
    'admin.payments.index',
    'admin.foods.index',
    'admin.ingredients.index',
]);
