<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins can list and search users', function () {
    User::factory()->create(['name' => 'Abebe Bikila', 'email' => 'abebe@example.com']);
    User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 3));

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'abebe']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'abebe@example.com'));
});

test('admins can view a user with profile and payments', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('user.id', $user->id));
});

test('admins can promote and demote other users', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['role' => 'admin'])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe('admin');

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['role' => 'user'])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe('user');
});

test('admins cannot change their own role', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), ['role' => 'user'])
        ->assertSessionHasErrors('role');

    expect($this->admin->fresh()->role)->toBe('admin');
});

test('role update validates allowed values', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), ['role' => 'superadmin'])
        ->assertSessionHasErrors('role');
});

test('admins can delete other users but not themselves', function () {
    $victim = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $victim))
        ->assertRedirect(route('admin.users.index'));

    expect(User::find($victim->id))->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->admin))
        ->assertSessionHasErrors('user');

    expect(User::find($this->admin->id))->not->toBeNull();
});
