<?php

use App\Models\Trainer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members without a coach are redirected to the gate', function () {
    $this->autoLinkActingMemberToCoach = false;

    $this->actingAs(User::factory()->create())
        ->get(route('member.home'))
        ->assertRedirect(route('member.no-coach'));
});

test('coachless members see the gate page', function () {
    $this->autoLinkActingMemberToCoach = false;

    $this->actingAs(User::factory()->create())
        ->get(route('member.no-coach'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/no-coach'));
});

test('coachless members get 403 on member api calls', function () {
    $this->autoLinkActingMemberToCoach = false;

    $this->actingAs(User::factory()->create())
        ->postJson(route('member.habits.store'), ['name' => 'Water'])
        ->assertForbidden();
});

test('members linked to a coach reach member pages', function () {
    $member = User::factory()->withCoach()->create();

    $this->actingAs($member)
        ->get(route('member.home'))
        ->assertOk();
});

test('trainers and admins bypass the gate', function () {
    $trainer = Trainer::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($trainer->user)
        ->get(route('member.home'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('member.home'))
        ->assertOk();
});

test('the active coach is shared with inertia pages', function () {
    $trainer = Trainer::factory()->create(['brand_name' => 'Coach Mike']);
    $member = User::factory()->withCoach($trainer)->create();

    $this->actingAs($member)
        ->get(route('member.home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('coach.slug', $trainer->slug)
            ->where('coach.name', 'Coach Mike')
            ->where('coach.user_id', $trainer->user_id));
});

test('coachless members see a null coach prop', function () {
    $this->autoLinkActingMemberToCoach = false;

    $this->actingAs(User::factory()->create())
        ->get(route('member.no-coach'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('coach', null));
});
