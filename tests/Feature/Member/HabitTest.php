<?php

use App\Models\Habit;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot manage habits', function () {
    $this->get(route('member.habits'))->assertRedirect(route('login'));
    $this->postJson(route('member.habits.store'), ['name' => 'Water'])
        ->assertUnauthorized();
});

test('user can create, rename and delete a habit', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('member.habits.store'), ['name' => '10k steps']);
    $response->assertCreated()->assertJsonPath('habit.name', '10k steps');
    $id = $response->json('habit.id');

    $this->patchJson(route('member.habits.update', $id), ['name' => '12k steps'])
        ->assertOk()->assertJsonPath('habit.name', '12k steps');

    $this->deleteJson(route('member.habits.destroy', $id))->assertOk();
    expect(Habit::find($id))->toBeNull();
});

test('toggling a habit flips the log for today', function () {
    $habit = Habit::factory()->create();
    $this->actingAs($habit->user);

    $this->postJson(route('member.habits.toggle', $habit->id), [])
        ->assertOk()->assertJsonCount(1, 'habit.dates');

    $this->postJson(route('member.habits.toggle', $habit->id), [])
        ->assertOk()->assertJsonCount(0, 'habit.dates');
});

test('users cannot touch other users habits', function () {
    $habit = Habit::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->patchJson(route('member.habits.update', $habit->id), ['name' => 'x'])
        ->assertForbidden();
    $this->deleteJson(route('member.habits.destroy', $habit->id))
        ->assertForbidden();
    $this->postJson(route('member.habits.toggle', $habit->id), [])
        ->assertForbidden();
});

test('habits page and home include habit props', function () {
    $user = User::factory()->create();
    Habit::factory()->for($user)->create(['name' => 'Water']);
    $this->actingAs($user);

    $this->get(route('member.habits'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/habits')
            ->has('habits', 1)
            ->where('habits.0.name', 'Water'));

    $this->get(route('member.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('habits', 1)
            ->where('habits.0.done', false));
});
