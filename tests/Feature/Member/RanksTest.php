<?php

use App\Models\Conversation;
use App\Models\Exercise;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\Gym;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSet;
use Inertia\Testing\AssertableInertia as Assert;

function lift(User $user, int $volume): void
{
    Exercise::where('slug', 'squat')->exists()
        || Exercise::factory()->create(['slug' => 'squat']);
    $session = WorkoutSession::factory()->create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
    ]);
    WorkoutSet::factory()->create([
        'workout_session_id' => $session->id,
        'exercise_id' => 'squat',
        'weight_kg' => $volume,
        'reps' => 1,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('member.ranks'))->assertRedirect(route('login'));
});

test('members opt in to see the leaderboards', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member.ranks'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/ranks')
            ->where('optedIn', false));

    $this->actingAs($user)
        ->postJson(route('member.ranks.opt-in'))
        ->assertOk();

    expect($user->refresh()->leaderboard_opted_in)->toBeTrue();

    $this->actingAs($user)
        ->get(route('member.ranks'))
        ->assertInertia(fn (Assert $page) => $page->where('optedIn', true));
});

test('friends board ranks accepted friends by volume', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $friend = User::factory()->create(['leaderboard_opted_in' => true]);
    $stranger = User::factory()->create(['leaderboard_opted_in' => true]);
    Friendship::create([
        'user_id' => $me->id,
        'friend_id' => $friend->id,
        'status' => 'accepted',
    ]);

    lift($me, 100);
    lift($friend, 500);
    lift($stranger, 900);

    $this->actingAs($me)
        ->get(route('member.ranks', ['scope' => 'friends', 'period' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 2)
            ->where('entries.0.user_id', $friend->id)
            ->where('entries.0.rank', 1)
            ->where('entries.1.user_id', $me->id)
            ->where('entries.1.is_you', true));
});

test('today board ranks by workouts logged today', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $friend = User::factory()->create(['leaderboard_opted_in' => true]);
    Friendship::create([
        'user_id' => $me->id,
        'friend_id' => $friend->id,
        'status' => 'accepted',
    ]);

    Exercise::where('slug', 'squat')->exists()
        || Exercise::factory()->create(['slug' => 'squat']);
    lift($friend, 100);
    WorkoutSession::factory()->create([
        'user_id' => $friend->id,
        'date' => now()->toDateString(),
    ]);
    WorkoutSession::factory()->create([
        'user_id' => $me->id,
        'date' => now()->subDays(10)->toDateString(),
    ]);

    $this->actingAs($me)
        ->get(route('member.ranks'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('period', 'today')
            ->where('metric', 'sessions')
            ->has('entries', 2)
            ->where('entries.0.user_id', $friend->id)
            ->where('entries.0.score', 2)
            ->where('entries.1.user_id', $me->id)
            ->where('entries.1.score', 0));
});

test('friend requests flow through search, store and accept', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $other = User::factory()->create([
        'username' => 'liftfan',
        'leaderboard_opted_in' => true,
    ]);

    $this->actingAs($me)
        ->getJson(route('member.ranks.friends.search', ['q' => 'liftfan']))
        ->assertOk()
        ->assertJsonCount(1, 'users');

    $this->actingAs($me)
        ->postJson(route('member.ranks.friends.store'), ['user_id' => $other->id])
        ->assertCreated();

    $friendship = Friendship::sole();
    expect($friendship->status)->toBe('pending');

    $this->actingAs($other)
        ->postJson(route('member.ranks.friends.accept', $friendship->id))
        ->assertOk();

    expect($friendship->refresh()->status)->toBe('accepted');
    expect($me->friendIds())->toBe([$other->id]);
});

test('friends board suggests unconnected members', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $friend = User::factory()->create(['leaderboard_opted_in' => true]);
    $pending = User::factory()->create(['leaderboard_opted_in' => true]);
    $stranger = User::factory()->create();

    Friendship::create([
        'user_id' => $me->id,
        'friend_id' => $friend->id,
        'status' => 'accepted',
    ]);
    Friendship::create([
        'user_id' => $me->id,
        'friend_id' => $pending->id,
        'status' => 'pending',
    ]);

    $this->actingAs($me)
        ->get(route('member.ranks', ['scope' => 'friends']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('suggestedUsers', 1)
            ->where('suggestedUsers.0.id', $stranger->id));
});

test('groups scope lists groups the member can join', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $other = User::factory()->create(['leaderboard_opted_in' => true]);

    $mine = Group::create(['name' => 'My Crew', 'owner_id' => $me->id]);
    $mine->members()->attach($me->id);
    $theirs = Group::create(['name' => 'Their Crew', 'owner_id' => $other->id]);
    $theirs->members()->attach($other->id);

    $this->actingAs($me)
        ->get(route('member.ranks', ['scope' => 'groups']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('joinableGroups', 1)
            ->where('joinableGroups.0.id', $theirs->id)
            ->where('joinableGroups.0.members', 1));
});

test('groups can be joined by id from the discovery cards', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $other = User::factory()->create(['leaderboard_opted_in' => true]);
    $group = Group::create(['name' => 'Open Crew', 'owner_id' => $other->id]);
    $group->members()->attach($other->id);

    $this->actingAs($me)
        ->postJson(route('member.ranks.groups.join'), ['group_id' => $group->id])
        ->assertCreated();

    expect($group->members()->where('users.id', $me->id)->exists())->toBeTrue();

    $conversation = Conversation::where('group_id', $group->id)->sole();
    expect($conversation->memberships()->where('user_id', $me->id)->exists())->toBeTrue();
});

test('groups rank only their members and join by code', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $mate = User::factory()->create(['leaderboard_opted_in' => true]);

    $response = $this->actingAs($me)
        ->postJson(route('member.ranks.groups.store'), ['name' => 'Crew']);
    $response->assertCreated();
    $group = Group::sole();

    $this->actingAs($mate)
        ->postJson(route('member.ranks.groups.join'), ['code' => $group->invite_code])
        ->assertCreated();

    lift($mate, 300);

    $this->actingAs($me)
        ->get(route('member.ranks', ['scope' => 'groups', 'period' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 1)
            ->has('entries', 2)
            ->where('entries.0.user_id', $mate->id)
            ->where('you.user_id', $me->id)
            ->where('you.rank', 2));
});

test('gym board scopes to the same gym', function () {
    $gym = Gym::create(['name' => 'Iron Den']);
    $me = User::factory()->create([
        'leaderboard_opted_in' => true,
        'gym_id' => $gym->id,
    ]);
    $mate = User::factory()->create([
        'leaderboard_opted_in' => true,
        'gym_id' => $gym->id,
    ]);
    $elsewhere = User::factory()->create([
        'leaderboard_opted_in' => true,
        'gym_id' => Gym::create(['name' => 'Power House'])->id,
    ]);

    lift($mate, 200);
    lift($elsewhere, 999);

    $this->actingAs($me)
        ->postJson(route('member.ranks.gym.store'), ['gym_id' => $gym->id])
        ->assertOk();

    $this->actingAs($me)
        ->get(route('member.ranks', ['scope' => 'gym', 'period' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', 2)
            ->where('entries.0.user_id', $mate->id)
            ->where('entries.0.streak', 1)
            ->where('you.user_id', $me->id));
});

test('group search matches unjoined groups by name', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $match = Group::create(['name' => 'Morning Warriors', 'owner_id' => User::factory()->create()->id]);
    $match->members()->attach(User::factory()->create()->id);
    Group::create(['name' => 'Evening Runners', 'owner_id' => User::factory()->create()->id]);

    $this->actingAs($me)
        ->getJson(route('member.ranks.groups.search', ['q' => 'warrior']))
        ->assertOk()
        ->assertJsonCount(1, 'groups')
        ->assertJsonPath('groups.0.id', $match->id)
        ->assertJsonPath('groups.0.members', 1);
});

test('group search excludes groups the member already belongs to', function () {
    $me = User::factory()->create(['leaderboard_opted_in' => true]);
    $mine = Group::create(['name' => 'My Squad', 'owner_id' => $me->id]);
    $mine->members()->attach($me->id);
    Group::create(['name' => 'My Other Squad', 'owner_id' => User::factory()->create()->id]);

    $this->actingAs($me)
        ->getJson(route('member.ranks.groups.search', ['q' => 'squad']))
        ->assertOk()
        ->assertJsonCount(1, 'groups');
});

test('group search requires a query', function () {
    $this->actingAs(User::factory()->create(['leaderboard_opted_in' => true]))
        ->getJson(route('member.ranks.groups.search'))
        ->assertUnprocessable();
});
