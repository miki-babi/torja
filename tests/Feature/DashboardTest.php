<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('members are redirected to their member home', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('member.home'));
});

test('admins are redirected to the admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $this->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});
