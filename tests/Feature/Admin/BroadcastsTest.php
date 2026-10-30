<?php

use App\Jobs\DispatchTelegramBroadcast;
use App\Jobs\RecallTelegramBroadcastRun;
use App\Jobs\SendTelegramBroadcastChunk;
use App\Models\TelegramBroadcast;
use App\Models\TelegramBroadcastDelivery;
use App\Models\TrackedEvent;
use App\Models\User;
use App\Models\UserTrait;
use App\Support\Telegram\BroadcastSender;
use App\Support\Telegram\SendsBroadcasts;
use App\Support\Telegram\TelegramApi;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function validBroadcastPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'October push',
        'type' => 'text',
        'body_html' => '<p>Hello <strong>team</strong></p>',
        'buttons' => [],
        'media' => [],
        'audience' => ['everyone' => true, 'segments' => [], 'user_ids' => []],
        'options' => ['disable_notification' => false, 'protect_content' => false],
        'send_mode' => 'draft',
        'recurrence' => 'none',
    ], $overrides);
}

test('non-admins cannot manage broadcasts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.broadcasts.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.broadcasts.store'), validBroadcastPayload())->assertForbidden();
});

test('admins can list broadcasts', function () {
    TelegramBroadcast::factory()->create(['name' => 'Welcome blast']);

    $this->actingAs($this->admin)
        ->get(route('admin.broadcasts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/broadcasts/index')
            ->has('broadcasts.data', 1));
});

test('store converts body html and saves a draft', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.store'), validBroadcastPayload([
            'body_html' => '<p>Hi <em>there</em> <span class="tg-spoiler">secret</span></p>',
        ]))
        ->assertRedirect();

    $broadcast = TelegramBroadcast::query()->sole();

    expect($broadcast->telegram_text)->toBe('Hi <i>there</i> <tg-spoiler>secret</tg-spoiler>')
        ->and($broadcast->status->value)->toBe('draft');
});

test('store requires message text for text broadcasts', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.store'), validBroadcastPayload(['body_html' => '']))
        ->assertSessionHasErrors('body_html');
});

test('store requires media for photo broadcasts', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.store'), validBroadcastPayload(['type' => 'photo']))
        ->assertSessionHasErrors('media');
});

test('store validates poll shape', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.store'), validBroadcastPayload([
            'type' => 'poll',
            'poll' => ['question' => '', 'options' => ['only one']],
        ]))
        ->assertSessionHasErrors(['poll.question', 'poll.options']);
});

test('store requires an effective audience', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.store'), validBroadcastPayload([
            'audience' => ['everyone' => false, 'segments' => [], 'user_ids' => []],
        ]))
        ->assertSessionHasErrors('audience');
});

test('audience resolves everyone, segments and picked users', function () {
    User::factory()->count(2)->create(); // unlinked — excluded
    $linked = User::factory()->create(['telegram_chat_id' => '111']);
    $picked = User::factory()->create(['telegram_chat_id' => '222']);
    UserTrait::factory()->for($linked)->create(['paid' => true]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.broadcasts.audience-count'), [
            'audience' => ['everyone' => true],
        ])
        ->assertJson(['count' => 2]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.broadcasts.audience-count'), [
            'audience' => ['segments' => ['paid']],
        ])
        ->assertJson(['count' => 1]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.broadcasts.audience-count'), [
            'audience' => ['segments' => ['paid'], 'user_ids' => [$picked->id]],
        ])
        ->assertJson(['count' => 2]);
});

test('users search only returns telegram-linked users', function () {
    User::factory()->create(['name' => 'No Telegram']);
    User::factory()->create(['name' => 'Abebe Linked', 'telegram_chat_id' => '1', 'telegram_username' => 'abebe']);

    $this->actingAs($this->admin)
        ->getJson(route('admin.broadcasts.users-search', ['q' => 'abebe']))
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.detail', '@abebe');
});

test('send now dispatches the broadcast job', function () {
    Bus::fake();

    $broadcast = TelegramBroadcast::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.send-now', $broadcast))
        ->assertRedirect();

    expect($broadcast->fresh()->status->value)->toBe('sending');
    Bus::assertDispatched(DispatchTelegramBroadcast::class);
});

test('send test posts the message to the admin chat', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]]),
    ]);

    $this->admin->forceFill(['telegram_chat_id' => '555'])->save();
    $broadcast = TelegramBroadcast::factory()->create(['telegram_text' => 'Hi <b>test</b>']);

    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.test', $broadcast))
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && $request['chat_id'] === '555'
        && $request['text'] === 'Hi <b>test</b>'
        && $request['parse_mode'] === 'HTML');
});

test('test send fails gracefully without a linked chat', function () {
    $broadcast = TelegramBroadcast::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.broadcasts.test', $broadcast))
        ->assertRedirect();
});

test('chunk job sends messages and marks deliveries', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]]),
    ]);

    $broadcast = TelegramBroadcast::factory()->create([
        'buttons' => [[['text' => 'Open', 'kind' => 'url', 'url' => 'https://x.test']]],
    ]);
    $run = $broadcast->runs()->create(['trigger' => 'manual', 'started_at' => now(), 'recipients_count' => 1]);
    $user = User::factory()->create(['telegram_chat_id' => '999']);
    $delivery = $run->deliveries()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => $user->id,
        'chat_id' => '999',
        'status' => 'pending',
    ]);

    (new SendTelegramBroadcastChunk($run->id, [$delivery->id]))
        ->handle(app(BroadcastSender::class), app(SendsBroadcasts::class));

    expect($delivery->fresh()->status)->toBe('sent')
        ->and($delivery->fresh()->message_ids)->toBe([42])
        ->and($run->fresh()->sent_count)->toBe(1)
        ->and($run->fresh()->finished_at)->not->toBeNull()
        ->and($broadcast->fresh()->status->value)->toBe('sent');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
        && $request['reply_markup']['inline_keyboard'][0][0]['url'] === 'https://x.test');
});

test('blocked users are marked instead of failing', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 403,
            'description' => 'Forbidden: bot was blocked by the user',
        ], 403),
    ]);

    $broadcast = TelegramBroadcast::factory()->create();
    $run = $broadcast->runs()->create(['trigger' => 'manual', 'started_at' => now(), 'recipients_count' => 1]);
    $delivery = $run->deliveries()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => User::factory()->create()->id,
        'chat_id' => '1',
        'status' => 'pending',
    ]);

    (new SendTelegramBroadcastChunk($run->id, [$delivery->id]))
        ->handle(app(BroadcastSender::class), app(SendsBroadcasts::class));

    expect($delivery->fresh()->status)->toBe('blocked')
        ->and($run->fresh()->blocked_count)->toBe(1);
});

test('rate limited sends stay pending and are requeued', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => false,
            'error_code' => 429,
            'description' => 'Too Many Requests',
            'parameters' => ['retry_after' => 3],
        ], 429),
    ]);
    Bus::fake([SendTelegramBroadcastChunk::class]);

    $broadcast = TelegramBroadcast::factory()->create();
    $run = $broadcast->runs()->create(['trigger' => 'manual', 'started_at' => now(), 'recipients_count' => 1]);
    $delivery = $run->deliveries()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => User::factory()->create()->id,
        'chat_id' => '1',
        'status' => 'pending',
    ]);

    (new SendTelegramBroadcastChunk($run->id, [$delivery->id]))
        ->handle(app(BroadcastSender::class), app(SendsBroadcasts::class));

    expect($delivery->fresh()->status)->toBe('pending');
    Bus::assertDispatched(SendTelegramBroadcastChunk::class);
});

test('dispatch due broadcasts queues scheduled work and advances nothing else', function () {
    Bus::fake();

    TelegramBroadcast::factory()->scheduled()->create();
    TelegramBroadcast::factory()->create(['status' => 'scheduled', 'next_run_at' => now()->addHour()]);

    $this->artisan('broadcasts:dispatch-due')->assertSuccessful();

    Bus::assertDispatched(DispatchTelegramBroadcast::class, fn ($job) => $job->trigger === 'schedule');
});

test('finalize advances recurring broadcasts to the next slot', function () {
    $broadcast = TelegramBroadcast::factory()->create([
        'status' => 'sending',
        'recurrence' => 'weekly',
        'next_run_at' => now(),
        'last_run_at' => now(),
    ]);
    $run = $broadcast->runs()->create(['trigger' => 'recurrence', 'started_at' => now()]);

    app(SendsBroadcasts::class)->finalize($broadcast, $run);

    $broadcast->refresh();

    expect($broadcast->status->value)->toBe('scheduled')
        ->and($broadcast->next_run_at->toDateString())->toBe(now()->addWeek()->toDateString());
});

test('webhook callback queries answer with toast and track clicks', function () {
    config(['services.telegram.webhook_secret' => null]);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $user = User::factory()->create(['telegram_id' => 4242, 'telegram_chat_id' => '4242']);
    $broadcast = TelegramBroadcast::factory()->create([
        'buttons' => [[[
            'text' => 'Count me in',
            'kind' => 'callback',
            'action' => 'track',
            'toast' => 'Nice!',
        ]]],
    ]);

    $this->postJson(route('telegram.webhook'), [
        'callback_query' => [
            'id' => 'cbq1',
            'data' => "bc.{$broadcast->id}.0.0",
            'from' => ['id' => 4242],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'answerCallbackQuery')
        && $request['callback_query_id'] === 'cbq1'
        && $request['text'] === 'Nice!');

    expect(TrackedEvent::query()
        ->where('user_id', $user->id)
        ->where('name', 'broadcast.button_clicked')
        ->exists())->toBeTrue();
});

test('recall deletes sent messages from chats', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $broadcast = TelegramBroadcast::factory()->create();
    $run = $broadcast->runs()->create(['trigger' => 'manual', 'started_at' => now(), 'finished_at' => now()]);
    $delivery = TelegramBroadcastDelivery::query()->create([
        'broadcast_id' => $broadcast->id,
        'run_id' => $run->id,
        'user_id' => User::factory()->create()->id,
        'chat_id' => '77',
        'status' => 'sent',
        'message_ids' => [10, 11],
        'sent_at' => now(),
    ]);

    (new RecallTelegramBroadcastRun($run->id, [$delivery->id]))->handle(app(TelegramApi::class));

    expect($delivery->fresh()->deleted_at)->not->toBeNull()
        ->and($run->fresh()->deleted_count)->toBe(1);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteMessage')
        && $request['chat_id'] === '77');
});
