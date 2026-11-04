<?php

use App\Jobs\NotifyChatMessage;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\Gym;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function befriend(User $a, User $b): void
{
    Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'status' => 'accepted']);
}

test('members need an accepted friendship to open a dm', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($me)
        ->postJson(route('member.messages.dm'), ['user_id' => $other->id])
        ->assertForbidden();

    Friendship::create(['user_id' => $other->id, 'friend_id' => $me->id, 'status' => 'pending']);

    $this->actingAs($me)
        ->postJson(route('member.messages.dm'), ['user_id' => $other->id])
        ->assertForbidden();

    befriend($me, $other);

    $this->actingAs($me)
        ->postJson(route('member.messages.dm'), ['user_id' => $other->id])
        ->assertOk()
        ->assertJsonStructure(['conversation_id']);
});

test('dm rooms are reused in both directions', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);

    $this->actingAs($a)->postJson(route('member.messages.dm'), ['user_id' => $b->id]);
    $this->actingAs($b)->postJson(route('member.messages.dm'), ['user_id' => $a->id]);

    expect(Conversation::where('type', 'dm')->count())->toBe(1);
});

test('dms stay usable after the friendship is removed', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);

    Friendship::query()->delete();

    $this->actingAs($a)
        ->postJson(route('member.messages.dm'), ['user_id' => $b->id])
        ->assertOk();

    $this->actingAs($a)
        ->postJson(route('member.messages.store', $conversation), ['body' => 'still works'])
        ->assertCreated();
});

test('sending, reading and receipts', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);

    $this->actingAs($a)
        ->postJson(route('member.messages.store', $conversation), ['body' => 'hey!'])
        ->assertCreated()
        ->assertJsonPath('message.body', 'hey!');

    expect($b->unreadChatCount())->toBe(1);

    $latest = $conversation->messages()->max('id');
    $this->actingAs($b)
        ->postJson(route('member.messages.read', $conversation), ['message_id' => $latest])
        ->assertOk();

    expect($b->unreadChatCount())->toBe(0)
        ->and($conversation->membershipFor($b)?->last_read_message_id)->toBe($latest);
});

test('the inbox and thread render for participants only', function () {
    [$a, $b, $stranger] = User::factory(3)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);
    $conversation->messages()->create(['user_id' => $a->id, 'body' => 'hi']);

    $this->actingAs($b)
        ->get(route('member.messages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/messages')
            ->has('conversations', 1)
            ->where('conversations.0.unread', 1));

    $this->actingAs($b)
        ->get(route('member.messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/messages-show')
            ->has('messages', 1));

    $this->actingAs($stranger)
        ->get(route('member.messages.show', $conversation))
        ->assertForbidden();
    $this->actingAs($stranger)
        ->postJson(route('member.messages.store', $conversation), ['body' => 'x'])
        ->assertForbidden();
});

test('messages can be edited by the sender and deleted by sender or moderator', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'oops']);

    $this->actingAs($b)
        ->patchJson(route('member.messages.update', [$conversation, $message]), ['body' => 'no'])
        ->assertForbidden();

    $this->actingAs($a)
        ->patchJson(route('member.messages.update', [$conversation, $message]), ['body' => 'fixed'])
        ->assertOk()
        ->assertJsonPath('message.edited', true);

    expect($message->refresh()->edited_at)->not->toBeNull();

    // In a DM nobody may delete the other side's messages.
    $this->actingAs($b)
        ->deleteJson(route('member.messages.destroy', [$conversation, $message]))
        ->assertForbidden();

    $this->actingAs($a)
        ->deleteJson(route('member.messages.destroy', [$conversation, $message]))
        ->assertOk();

    expect($message->refresh()->trashed())->toBeTrue();
});

test('squad owners moderate their group chat', function () {
    [$owner, $member] = User::factory(2)->create()->all();
    $group = Group::create(['name' => 'Squad', 'owner_id' => $owner->id]);
    $group->members()->attach([$owner->id, $member->id]);
    $conversation = Conversation::forGroup($group);
    $conversation->addParticipant($owner);
    $conversation->addParticipant($member);

    $message = $conversation->messages()->create(['user_id' => $member->id, 'body' => 'spam']);

    $this->actingAs($owner)
        ->deleteJson(route('member.messages.destroy', [$conversation, $message]))
        ->assertOk();
});

test('group chats are provisioned on create, join and leave', function () {
    $owner = User::factory()->create();
    $joiner = User::factory()->create();

    $this->actingAs($owner)
        ->postJson(route('member.ranks.groups.store'), ['name' => 'Wolves'])
        ->assertCreated();

    $group = Group::first();
    $conversation = Conversation::where('group_id', $group->id)->firstOrFail();
    expect($conversation->participants()->count())->toBe(1);

    $this->actingAs($joiner)
        ->postJson(route('member.ranks.groups.join'), ['code' => $group->invite_code])
        ->assertCreated();

    expect($conversation->participants()->count())->toBe(2);

    $this->actingAs($joiner)
        ->deleteJson(route('member.ranks.groups.destroy', $group))
        ->assertOk();

    expect($conversation->participants()->count())->toBe(1);
});

test('gym picker: search, create, dedupe and chat seat', function () {
    $user = User::factory()->create();
    Gym::create(['name' => 'Flex Addis', 'created_by' => $user->id]);

    $this->actingAs($user)
        ->getJson(route('member.ranks.gyms.index', ['q' => 'flex']))
        ->assertOk()
        ->assertJsonCount(1, 'gyms');

    $this->actingAs($user)
        ->postJson(route('member.ranks.gym.store'), ['name' => 'flex addis'])
        ->assertOk();

    expect(Gym::count())->toBe(1);

    $this->actingAs($user)
        ->postJson(route('member.ranks.gym.store'), ['name' => 'Iron House'])
        ->assertOk();

    $user->refresh();
    $gym = $user->gym;
    expect($gym->name)->toBe('Iron House');
    expect(Conversation::forGym($gym)->isParticipant($user))->toBeTrue();

    // Leaving the gym frees the chat seat.
    $this->actingAs($user)->deleteJson(route('member.ranks.gym.destroy'));
    expect(Conversation::forGym($gym)->isParticipant($user->refresh()))->toBeFalse();
});

test('gym creators moderate their gym chat', function () {
    [$creator, $member] = User::factory(2)->create()->all();
    $gym = Gym::create(['name' => 'Powerhouse', 'created_by' => $creator->id]);
    $creator->setGym($gym);
    $member->setGym($gym);

    $conversation = Conversation::forGym($gym);
    $message = $conversation->messages()->create(['user_id' => $member->id, 'body' => 'hey']);

    $this->actingAs($creator)
        ->deleteJson(route('member.messages.destroy', [$conversation, $message]))
        ->assertOk();
});

test('attachments upload to the public disk', function () {
    Storage::fake('public');
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);

    $this->actingAs($a)
        ->post(route('member.messages.store', $conversation), [
            'attachments' => [UploadedFile::fake()->image('lift.png', 100, 50)],
        ])
        ->assertCreated()
        ->assertJsonPath('message.attachments.0.kind', 'image')
        ->assertJsonPath('message.attachments.0.width', 100);

    $message = Message::first();
    Storage::disk('public')->assertExists($message->attachments->first()->path);
});

test('reactions toggle', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'hi']);

    $this->actingAs($b)
        ->postJson(route('member.messages.react', [$conversation, $message]), ['emoji' => '💪'])
        ->assertOk()
        ->assertJsonPath('reactions.0.count', 1);

    $this->actingAs($b)
        ->postJson(route('member.messages.react', [$conversation, $message]), ['emoji' => '💪'])
        ->assertOk()
        ->assertJsonCount(0, 'reactions');
});

test('mute flag toggles per conversation', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $conversation = Conversation::dmBetween($a, $b);

    $this->actingAs($b)
        ->postJson(route('member.messages.mute', $conversation))
        ->assertOk()
        ->assertJsonPath('muted', true);

    expect($conversation->membershipFor($b)?->muted_at)->not->toBeNull();

    $this->actingAs($b)
        ->postJson(route('member.messages.unmute', $conversation))
        ->assertOk()
        ->assertJsonPath('muted', false);
});

test('sending a message queues a telegram push per other participant', function () {
    Queue::fake();
    [$a, $b, $c] = User::factory(3)->create()->all();
    $group = Group::create(['name' => 'Crew', 'owner_id' => $a->id]);
    $group->members()->attach([$a->id, $b->id, $c->id]);
    $conversation = Conversation::forGroup($group);
    $conversation->addParticipant($a);
    $conversation->addParticipant($b);
    $conversation->addParticipant($c);

    $this->actingAs($a)
        ->postJson(route('member.messages.store', $conversation), ['body' => 'hello crew'])
        ->assertCreated();

    Queue::assertPushed(NotifyChatMessage::class, 2);
});

test('notification job respects mute, throttle and read state', function () {
    [$a, $b] = User::factory(2)->create()->all();
    befriend($a, $b);
    $b->forceFill(['telegram_id' => 12345])->save();
    $conversation = Conversation::dmBetween($a, $b);
    $membership = $conversation->membershipFor($b);
    $message = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'yo']);

    // Muted → no send, no notify stamp.
    $membership->update(['muted_at' => now()]);
    (new NotifyChatMessage($message->id, $b->id))->handle();
    expect($membership->refresh()->last_notified_at)->toBeNull();

    // Already read → skip.
    $membership->update(['muted_at' => null, 'last_read_message_id' => $message->id]);
    (new NotifyChatMessage($message->id, $b->id))->handle();
    expect($membership->refresh()->last_notified_at)->toBeNull();
});

test('the inbox suggests unconnected members from the social graph', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create();
    $friendOfFriend = User::factory()->create();

    befriend($me, $friend);
    befriend($friend, $friendOfFriend);

    $this->actingAs($me)
        ->get(route('member.messages'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestions.0.id', $friendOfFriend->id)
            ->where('suggestions.0.reason.key', 'mutual_friends'));
});

test('the inbox fills suggestions with unconnected members', function () {
    $me = User::factory()->create();
    $friend = User::factory()->create();
    $pending = User::factory()->create();
    $stranger = User::factory()->create();

    befriend($me, $friend);
    Friendship::create([
        'user_id' => $me->id,
        'friend_id' => $pending->id,
        'status' => 'pending',
    ]);

    $this->actingAs($me)
        ->get(route('member.messages'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('suggestions', 1)
            ->where('suggestions.0.id', $stranger->id));
});

test('the inbox suggests gym mates with the gym as the reason', function () {
    $gym = Gym::create(['name' => 'Iron Den']);
    $me = User::factory()->create(['gym_id' => $gym->id]);
    $mate = User::factory()->create(['gym_id' => $gym->id]);

    $this->actingAs($me)
        ->get(route('member.messages'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestions.0.id', $mate->id)
            ->where('suggestions.0.reason.key', 'gym')
            ->where('suggestions.0.reason.name', 'Iron Den'));
});

test('the inbox suggests groupmates with the group as the reason', function () {
    $me = User::factory()->create();
    $mate = User::factory()->create();
    $group = Group::create(['name' => 'Morning Warriors', 'owner_id' => $me->id]);
    $group->members()->attach([$me->id, $mate->id]);

    $this->actingAs($me)
        ->get(route('member.messages'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suggestions.0.id', $mate->id)
            ->where('suggestions.0.reason.key', 'group')
            ->where('suggestions.0.reason.name', 'Morning Warriors'));
});

test('the inbox suggests nearby members with the distance as the reason', function () {
    $me = User::factory()->create([
        'latitude' => 9.0301,
        'longitude' => 38.7654,
    ]);
    // ~2 km away.
    $near = User::factory()->create([
        'latitude' => 9.0481,
        'longitude' => 38.7654,
    ]);

    $this->actingAs($me)
        ->get(route('member.messages'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasLocation', true)
            ->where('suggestions.0.id', $near->id)
            ->where('suggestions.0.reason.key', 'nearby'));
});
