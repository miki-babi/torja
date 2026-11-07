<?php

use App\Jobs\NotifyChatMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{0: User, 1: User} */
function coachClient(): array
{
    $trainer = Trainer::factory()->create();
    $client = User::factory()->withCoach($trainer)->create();

    return [$client, $trainer->user];
}

test('members can only open a dm with their coach', function () {
    [$me, $coachUser] = coachClient();
    $stranger = User::factory()->create();

    $this->actingAs($me)
        ->postJson(route('member.messages.dm'), ['user_id' => $stranger->id])
        ->assertForbidden();

    $this->actingAs($me)
        ->postJson(route('member.messages.dm'), ['user_id' => $coachUser->id])
        ->assertOk()
        ->assertJsonStructure(['conversation_id']);
});

test('trainers can dm their clients', function () {
    [$client, $coachUser] = coachClient();

    $this->actingAs($coachUser)
        ->postJson(route('member.messages.dm'), ['user_id' => $client->id])
        ->assertOk()
        ->assertJsonStructure(['conversation_id']);

    $this->actingAs($coachUser)
        ->postJson(route('member.messages.dm'), ['user_id' => User::factory()->create()->id])
        ->assertForbidden();
});

test('the chat tab lands members inside the coach thread', function () {
    [$me, $coachUser] = coachClient();

    $response = $this->actingAs($me)->get(route('member.messages'));

    $conversation = Conversation::sole();

    expect($conversation->type->value)->toBe('dm')
        ->and($conversation->isParticipant($me))->toBeTrue()
        ->and($conversation->isParticipant($coachUser))->toBeTrue();

    $response->assertRedirect(route('member.messages.show', $conversation));
});

test('staff still get their inbox', function () {
    [$client, $coachUser] = coachClient();
    Conversation::dmBetween($client, $coachUser)
        ->messages()->create(['user_id' => $client->id, 'body' => 'hi coach']);

    $this->actingAs($coachUser)
        ->get(route('member.messages'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/messages')
            ->has('conversations', 1)
            ->where('conversations.0.unread', 1));
});

test('dm rooms are reused in both directions', function () {
    [$client, $coachUser] = coachClient();

    $this->actingAs($client)->postJson(route('member.messages.dm'), ['user_id' => $coachUser->id]);
    $this->actingAs($coachUser)->postJson(route('member.messages.dm'), ['user_id' => $client->id]);

    expect(Conversation::count())->toBe(1);
});

test('members cannot open non-coach rooms', function () {
    [$me] = coachClient();
    $stranger = User::factory()->create();
    $room = Conversation::dmBetween($me, $stranger);

    $this->actingAs($me)
        ->get(route('member.messages.show', $room))
        ->assertForbidden();
    $this->actingAs($me)
        ->postJson(route('member.messages.store', $room), ['body' => 'x'])
        ->assertForbidden();
    $this->actingAs($me)
        ->postJson(route('member.messages.mute', $room))
        ->assertForbidden();
});

test('sending, reading and receipts', function () {
    [$me, $coachUser] = coachClient();
    $conversation = Conversation::dmBetween($me, $coachUser);

    $this->actingAs($me)
        ->postJson(route('member.messages.store', $conversation), ['body' => 'hey!'])
        ->assertCreated()
        ->assertJsonPath('message.body', 'hey!');

    expect($coachUser->unreadChatCount())->toBe(1);

    $latest = $conversation->messages()->max('id');
    $this->actingAs($coachUser)
        ->postJson(route('member.messages.read', $conversation), ['message_id' => $latest])
        ->assertOk();

    expect($coachUser->unreadChatCount())->toBe(0)
        ->and($conversation->membershipFor($coachUser)?->last_read_message_id)->toBe($latest);
});

test('the member unread badge only counts the coach thread', function () {
    [$me, $coachUser] = coachClient();
    $otherRoom = Conversation::dmBetween($me, User::factory()->create());
    $otherRoom->messages()->create(['user_id' => $otherRoom->otherParticipant($me)->id, 'body' => 'noise']);

    expect($me->unreadChatCount())->toBe(0);

    Conversation::dmBetween($me, $coachUser)
        ->messages()->create(['user_id' => $coachUser->id, 'body' => 'from coach']);

    expect($me->unreadChatCount())->toBe(1);
});

test('the thread renders for participants only', function () {
    [$me, $coachUser] = coachClient();
    $stranger = User::factory()->create();
    $conversation = Conversation::dmBetween($me, $coachUser);
    $conversation->messages()->create(['user_id' => $coachUser->id, 'body' => 'hi']);

    $this->actingAs($me)
        ->get(route('member.messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member/messages-show')
            ->has('messages', 1)
            ->where('conversation.back_url', route('member.home')));

    $this->actingAs($coachUser)
        ->get(route('member.messages.show', $conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation.back_url', route('member.messages')));

    $this->actingAs($stranger)
        ->get(route('member.messages.show', $conversation))
        ->assertForbidden();
});

test('messages can be edited by the sender and deleted by sender or admin', function () {
    [$me, $coachUser] = coachClient();
    $conversation = Conversation::dmBetween($me, $coachUser);
    $message = $conversation->messages()->create(['user_id' => $me->id, 'body' => 'oops']);

    $this->actingAs($coachUser)
        ->patchJson(route('member.messages.update', [$conversation, $message]), ['body' => 'no'])
        ->assertForbidden();

    $this->actingAs($me)
        ->patchJson(route('member.messages.update', [$conversation, $message]), ['body' => 'fixed'])
        ->assertOk()
        ->assertJsonPath('message.edited', true);

    expect($message->refresh()->edited_at)->not->toBeNull();

    $this->actingAs($coachUser)
        ->deleteJson(route('member.messages.destroy', [$conversation, $message]))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->deleteJson(route('member.messages.destroy', [$conversation, $message]))
        ->assertOk();

    expect($message->refresh()->trashed())->toBeTrue();
});

test('attachments upload to the public disk', function () {
    Storage::fake('public');
    [$me, $coachUser] = coachClient();
    $conversation = Conversation::dmBetween($me, $coachUser);

    $this->actingAs($me)
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
    [$me, $coachUser] = coachClient();
    $conversation = Conversation::dmBetween($me, $coachUser);
    $message = $conversation->messages()->create(['user_id' => $me->id, 'body' => 'hi']);

    $this->actingAs($coachUser)
        ->postJson(route('member.messages.react', [$conversation, $message]), ['emoji' => '💪'])
        ->assertOk()
        ->assertJsonPath('reactions.0.count', 1);

    $this->actingAs($coachUser)
        ->postJson(route('member.messages.react', [$conversation, $message]), ['emoji' => '💪'])
        ->assertOk()
        ->assertJsonCount(0, 'reactions');
});

test('mute flag toggles per conversation', function () {
    [$me, $coachUser] = coachClient();
    $conversation = Conversation::dmBetween($me, $coachUser);

    $this->actingAs($me)
        ->postJson(route('member.messages.mute', $conversation))
        ->assertOk()
        ->assertJsonPath('muted', true);

    expect($conversation->membershipFor($me)?->muted_at)->not->toBeNull();

    $this->actingAs($me)
        ->postJson(route('member.messages.unmute', $conversation))
        ->assertOk()
        ->assertJsonPath('muted', false);
});

test('sending a message queues a telegram push to the other side', function () {
    Queue::fake();
    [$me, $coachUser] = coachClient();
    $conversation = Conversation::dmBetween($me, $coachUser);

    $this->actingAs($me)
        ->postJson(route('member.messages.store', $conversation), ['body' => 'hello coach'])
        ->assertCreated();

    Queue::assertPushed(NotifyChatMessage::class, 1);
});

test('notification job respects mute, throttle and read state', function () {
    [$me, $coachUser] = coachClient();
    $me->forceFill(['telegram_id' => 12345])->save();
    $conversation = Conversation::dmBetween($me, $coachUser);
    $membership = $conversation->membershipFor($me);
    $message = $conversation->messages()->create(['user_id' => $coachUser->id, 'body' => 'yo']);

    // Muted → no send, no notify stamp.
    $membership->update(['muted_at' => now()]);
    (new NotifyChatMessage($message->id, $me->id))->handle();
    expect($membership->refresh()->last_notified_at)->toBeNull();

    // Already read → skip.
    $membership->update(['muted_at' => null, 'last_read_message_id' => $message->id]);
    (new NotifyChatMessage($message->id, $me->id))->handle();
    expect($membership->refresh()->last_notified_at)->toBeNull();
});

test('member recipients are silenced outside the coach thread', function () {
    [$me, $coachUser] = coachClient();
    $me->forceFill(['telegram_id' => 12345])->save();

    $foreign = Conversation::dmBetween($me, User::factory()->create());
    $membership = $foreign->membershipFor($me);
    $message = $foreign->messages()->create([
        'user_id' => $foreign->otherParticipant($me)->id,
        'body' => 'not your coach',
    ]);

    (new NotifyChatMessage($message->id, $me->id))->handle();
    expect($membership->refresh()->last_notified_at)->toBeNull();
});
