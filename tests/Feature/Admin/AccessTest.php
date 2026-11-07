<?php

use App\Models\User;

test('guests are redirected to login from the admin area', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
});

test('non-admin users get a 403 from the admin area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('unverified admins can reach the admin area', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('admin.trainers.index'))
        ->assertOk();
});

test('admins land on the trainers list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.trainers.index'));
});

test('all admin routes reject non-admin users', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with([
    'admin.trainers.index',
]);
