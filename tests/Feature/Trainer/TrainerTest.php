<?php

use App\Jobs\SendTrainerBroadcast;
use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramAccess;
use App\Models\ExerciseProgramWorkout;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function tenantInitData(string $token, array $user = []): string
{
    $data = [
        'auth_date' => (string) time(),
        'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
        'user' => json_encode([
            'id' => 555001,
            'username' => 'clientone',
            'first_name' => 'Client',
            'last_name' => 'One',
            ...$user,
        ]),
    ];

    $pairs = [];

    foreach ($data as $key => $value) {
        $pairs[$key] = "{$key}={$value}";
    }

    ksort($pairs);

    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $data['hash'] = hash_hmac('sha256', implode("\n", $pairs), $secret);

    return http_build_query($data);
}

test('guests are redirected to login from the trainer area', function () {
    $this->get('/trainer')->assertRedirect(route('login'));
});

test('non-trainers get 403 from the trainer area', function () {
    $this->actingAs(User::factory()->create())
        ->get('/trainer')
        ->assertForbidden();
});

test('trainers reach the dashboard', function () {
    $user = User::factory()->trainer()->create();

    $this->actingAs($user)
        ->get('/trainer')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('trainer/dashboard'));
});

test('trainer registration creates a trainer user and tenant', function () {
    $this->post('/trainer/register', [
        'name' => 'Coach Mike',
        'email' => 'mike@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('trainer.bot.edit'));

    $user = User::where('email', 'mike@example.com')->first();

    expect($user->isTrainer())->toBeTrue();
    expect($user->trainer)->not->toBeNull();
    expect($user->trainer->slug)->toStartWith('coach-mike-');
});

test('connecting a bot validates the token and registers webhook + menu button', function () {
    Http::fake([
        'https://api.telegram.org/bot12345:abcdefghijklmnopqrstuvwx/getMe' => Http::response([
            'ok' => true,
            'result' => ['id' => 123, 'username' => 'coach_bot', 'first_name' => 'Coach'],
        ]),
        'https://api.telegram.org/bot12345:abcdefghijklmnopqrstuvwx/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $user = User::factory()->trainer()->create();

    $this->actingAs($user)
        ->post('/trainer/bot', ['bot_token' => '12345:abcdefghijklmnopqrstuvwx'])
        ->assertRedirect();

    $trainer = $user->trainer->fresh();

    expect($trainer->bot_token)->toBe('12345:abcdefghijklmnopqrstuvwx');
    expect($trainer->bot_username)->toBe('coach_bot');
    expect($trainer->webhook_secret)->not->toBeNull();
    expect($trainer->webhook_registered_at)->not->toBeNull();
    expect($trainer->menu_button_set_at)->not->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook')
        && str_contains($request->url(), 'bot12345:abcdefghijklmnopqrstuvwx'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'setChatMenuButton'));
});

test('bot connect fails when Telegram rejects the token', function () {
    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => false,
            'description' => 'Unauthorized',
        ]),
    ]);

    $user = User::factory()->trainer()->create();

    $this->actingAs($user)
        ->post('/trainer/bot', ['bot_token' => '12345:badtokenbadtokenbadtoken1'])
        ->assertRedirect();

    expect($user->trainer->fresh()->bot_token)->toBeNull();
});

test('bot setup page reports live webhook status', function () {
    $trainer = Trainer::factory()->withBot()->create();

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => [
                'url' => $trainer->webhookUrl(),
                'pending_update_count' => 0,
            ],
        ]),
    ]);

    $this->actingAs($trainer->user)
        ->get('/trainer/bot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('trainer/bot-setup')
            ->where('webhook.status', 'ok'));
});

test('bot setup page reports a mismatched webhook', function () {
    $trainer = Trainer::factory()->withBot()->create();

    Http::fake([
        'https://api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['url' => 'https://other.example/hook', 'pending_update_count' => 3],
        ]),
    ]);

    $this->actingAs($trainer->user)
        ->get('/trainer/bot')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('webhook.status', 'mismatch'));
});

test('tenant webhook links the sender as a client and replies via the trainer bot', function () {
    $trainer = Trainer::factory()->withBot()->create();
    $token = $trainer->bot_token;

    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $this->postJson("/telegram/webhook/t/{$trainer->slug}", [
        'message' => [
            'chat' => ['id' => 777, 'type' => 'private'],
            'text' => '/start',
            'from' => ['id' => 555001, 'username' => 'clientone', 'first_name' => 'Client'],
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => $trainer->webhook_secret])
        ->assertOk();

    $user = User::where('telegram_id', 555001)->first();

    expect($user)->not->toBeNull();
    expect($trainer->clients()->where('users.id', $user->id)->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), "bot{$token}/sendMessage"));
});

test('tenant webhook rejects a bad secret', function () {
    $trainer = Trainer::factory()->withBot()->create();

    $this->postJson("/telegram/webhook/t/{$trainer->slug}", [
        'message' => [
            'chat' => ['id' => 777, 'type' => 'private'],
            'text' => '/start',
            'from' => ['id' => 555001],
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
        ->assertForbidden();
});

test('mini app sign-in through a tenant links the client', function () {
    $trainer = Trainer::factory()->withBot()->create();

    $this->postJson('/auth/telegram/webapp', [
        'init_data' => tenantInitData($trainer->bot_token),
        'tenant' => $trainer->slug,
    ])->assertOk();

    $user = User::where('telegram_id', 555001)->first();

    expect($user)->not->toBeNull();
    expect($trainer->clients()->where('users.id', $user->id)->exists())->toBeTrue();
});

test('visiting /t/{slug} while logged in links the member as a client', function () {
    $trainer = Trainer::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get("/t/{$trainer->slug}")
        ->assertRedirect(route('member.home'));

    expect($trainer->clients()->where('users.id', $member->id)->exists())->toBeTrue();
});

test('assigning a program grants each client access idempotently', function () {
    $trainer = Trainer::factory()->withBot()->create();
    $owner = $trainer->user;
    $client = User::factory()->create();
    $trainer->clients()->attach($client->id);

    $program = ExerciseProgram::factory()->create(['user_id' => $owner->id, 'price_etb' => 0]);
    ExerciseProgramWorkout::factory()->create([
        'exercise_program_id' => $program->id,
        'title' => 'Day 1',
    ]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $this->actingAs($owner)
        ->postJson("/trainer/programs/{$program->id}/assign", [
            'client_ids' => [$client->id],
        ])
        ->assertOk()
        ->assertJson(['granted' => 1, 'already_claimed' => 0]);

    expect(ExerciseProgramAccess::where('user_id', $client->id)
        ->where('exercise_program_id', $program->id)->exists())->toBeTrue();
    expect($client->exercisePlaylists()->count())->toBe(1);

    $this->actingAs($owner)
        ->postJson("/trainer/programs/{$program->id}/assign", [
            'client_ids' => [$client->id],
        ])
        ->assertOk()
        ->assertJson(['granted' => 0, 'already_claimed' => 1]);

    expect($client->exercisePlaylists()->count())->toBe(1);
});

test('a linked client can claim a free coach program without Pro', function () {
    $trainer = Trainer::factory()->create();
    $client = User::factory()->create();
    $trainer->clients()->attach($client->id);

    $program = ExerciseProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'price_etb' => 0,
        'share_token' => 'tok-123',
    ]);

    $this->actingAs($client)
        ->postJson('/shared/programs/tok-123/save')
        ->assertCreated();

    expect(ExerciseProgramAccess::where('user_id', $client->id)
        ->where('exercise_program_id', $program->id)->exists())->toBeTrue();
});

test('a non-client without Pro cannot claim a coach program', function () {
    $trainer = Trainer::factory()->create();
    $outsider = User::factory()->create();

    $program = ExerciseProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'price_etb' => 0,
        'share_token' => 'tok-xyz',
    ]);

    $this->actingAs($outsider)
        ->postJson('/shared/programs/tok-xyz/save')
        ->assertForbidden();
});

test('program checkout creates a payable payment at the program price', function () {
    $trainer = Trainer::factory()->create();
    $buyer = User::factory()->create();

    $program = ExerciseProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'price_etb' => 1500,
        'share_token' => 'tok-paid',
    ]);

    Http::fake([
        'https://api.chapa.co/*' => Http::response([
            'status' => 'success',
            'data' => ['checkout_url' => 'https://checkout.chapa.co/x'],
        ]),
    ]);

    config()->set('services.chapa.secret_key', 'test-secret');

    $this->actingAs($buyer)
        ->post('/shared/programs/tok-paid/checkout', ['provider' => 'chapa'])
        ->assertRedirect('https://checkout.chapa.co/x');

    $payment = $buyer->payments()->latest('id')->first();

    expect($payment->purpose)->toBe('program_purchase');
    expect($payment->payable_id)->toBe($program->id);
    expect((float) $payment->amount)->toBe(1500.0);
    expect($trainer->clients()->where('users.id', $buyer->id)->exists())->toBeTrue();
});

test('broadcasts dispatch the fan-out job to linked clients only', function () {
    Bus::fake();

    $trainer = Trainer::factory()->withBot()->create();
    $this->actingAs($trainer->user)
        ->post('/trainer/broadcasts', ['body' => 'Morning all!'])
        ->assertRedirect();

    Bus::assertDispatched(SendTrainerBroadcast::class);
});

test('broadcast job sends to linked clients with telegram ids', function () {
    $trainer = Trainer::factory()->withBot()->create();
    $token = $trainer->bot_token;

    $linked = User::factory()->create(['telegram_id' => 9001, 'telegram_chat_id' => '9001']);
    $noTelegram = User::factory()->create(['telegram_id' => null, 'telegram_chat_id' => null]);
    $outsider = User::factory()->create(['telegram_id' => 9999, 'telegram_chat_id' => '9999']);

    $trainer->clients()->attach([$linked->id, $noTelegram->id]);

    Http::fake([
        'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $broadcast = $trainer->broadcasts()->create(['body' => 'hello']);

    (new SendTrainerBroadcast($broadcast->id))->handle();

    expect($broadcast->fresh()->sent_count)->toBe(1);
    expect($broadcast->fresh()->failed_count)->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), "bot{$token}/sendMessage")
        && $request['chat_id'] === '9001');
});

test('trainer and client can open a DM', function () {
    $trainer = Trainer::factory()->create();
    $client = User::factory()->create();
    $trainer->clients()->attach($client->id);

    $this->actingAs($trainer->user)
        ->postJson('/member/messages/dm', ['user_id' => $client->id])
        ->assertOk()
        ->assertJsonStructure(['conversation_id']);
});

test('unrelated users still cannot open a DM', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($a)
        ->postJson('/member/messages/dm', ['user_id' => $b->id])
        ->assertForbidden();
});

test('a trainer cannot see another trainer’s programs or clients', function () {
    $mine = Trainer::factory()->create();
    $theirs = Trainer::factory()->create();
    $theirClient = User::factory()->create();
    $theirs->clients()->attach($theirClient->id);

    $foreign = ExerciseProgram::factory()->create(['user_id' => $theirs->user_id]);

    $this->actingAs($mine->user)
        ->get("/trainer/programs/{$foreign->id}/edit")
        ->assertNotFound();

    $this->actingAs($mine->user)
        ->get("/trainer/clients/{$theirClient->id}")
        ->assertNotFound();
});

test('an admin can promote a member to trainer by email', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create(['email' => 'promote@example.com']);

    $this->actingAs($admin)
        ->post('/admin/trainers/promote', ['email' => 'promote@example.com'])
        ->assertRedirect();

    expect($member->fresh()->role)->toBe('trainer');
    expect($member->fresh()->trainer)->not->toBeNull();
});
