<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('services.telegram.bot_username', 'trainpro_bot');
    config()->set('services.telegram.bot_token', 'test-token');

    Http::fake();
});

function signedInitData(array $user = [], ?int $authDate = null, ?string $startParam = null): string
{
    $data = [
        'auth_date' => (string) ($authDate ?? time()),
        'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
        'user' => json_encode([
            'id' => 555001,
            'username' => 'janedoe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'language_code' => 'en',
            ...$user,
        ]),
    ];

    if ($startParam !== null) {
        $data['start_param'] = $startParam;
    }

    $pairs = [];

    foreach ($data as $key => $value) {
        $pairs[$key] = "{$key}={$value}";
    }

    ksort($pairs);

    $secret = hash_hmac('sha256', 'test-token', 'WebAppData', true);
    $data['hash'] = hash_hmac('sha256', implode("\n", $pairs), $secret);

    return http_build_query($data);
}

function telegramUpdate(string $text, array $from = [], int $chatId = 12345): array
{
    return [
        'message' => [
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'text' => $text,
            'from' => [
                'id' => 555001,
                'username' => 'janedoe',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                ...$from,
            ],
        ],
    ];
}

test('init returns a bot deep link and pending token', function () {
    $response = $this->postJson(route('telegram.login.init'));

    $response->assertOk();
    $response->assertJsonStructure(['url', 'token', 'expires_in']);

    $data = $response->json();

    expect($data['url'])->toStartWith('https://t.me/trainpro_bot?start=login_')
        ->and(Cache::get("tg-login:{$data['token']}"))->toBe(['user_id' => null]);
});

test('init fails when telegram is not configured', function () {
    config()->set('services.telegram.bot_username', null);

    $this->postJson(route('telegram.login.init'))->assertStatus(503);
});

test('status reports pending then ready after the bot binds the token', function () {
    $token = 'login_'.Str::random(48);
    Cache::put("tg-login:{$token}", ['user_id' => null], 600);

    $this->getJson(route('telegram.login.status', ['token' => $token]))
        ->assertOk()
        ->assertJson(['status' => 'pending']);

    $user = User::factory()->withTelegram()->create(['telegram_id' => 555001]);

    $this->postJson(route('telegram.webhook'), telegramUpdate("/start {$token}"))
        ->assertOk();

    $this->getJson(route('telegram.login.status', ['token' => $token]))
        ->assertOk()
        ->assertJson(['status' => 'ready']);
});

test('complete logs in the bound user and consumes the token', function () {
    $user = User::factory()->withTelegram()->create(['telegram_id' => 555001]);

    $token = 'login_'.Str::random(48);
    Cache::put("tg-login:{$token}", ['user_id' => null], 600);

    $this->postJson(route('telegram.webhook'), telegramUpdate("/start {$token}"));

    $response = $this->postJson(route('telegram.login.complete'), ['token' => $token]);

    $response->assertOk()->assertJsonStructure(['redirect']);
    $this->assertAuthenticatedAs($user);
    expect(Cache::get("tg-login:{$token}"))->toBeNull();

    $this->post(route('logout'));

    $this->postJson(route('telegram.login.complete'), ['token' => $token])
        ->assertStatus(410);
});

test('complete returns pending before the user confirms in telegram', function () {
    $token = 'login_'.Str::random(48);
    Cache::put("tg-login:{$token}", ['user_id' => null], 600);

    $this->postJson(route('telegram.login.complete'), ['token' => $token])
        ->assertStatus(409);

    $this->assertGuest();
});

test('webhook auto-creates an account for unknown telegram users', function () {
    $token = 'login_'.Str::random(48);
    Cache::put("tg-login:{$token}", ['user_id' => null], 600);

    $this->postJson(route('telegram.webhook'), telegramUpdate("/start {$token}"))
        ->assertOk();

    $user = User::firstWhere('telegram_id', 555001);

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->username)->toBe('janedoe')
        ->and($user->email)->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->telegram_username)->toBe('janedoe')
        ->and($user->telegram_chat_id)->toBe('12345');

    expect(Cache::get("tg-login:{$token}"))->toBe(['user_id' => $user->id]);
});

test('username is left empty when the telegram handle is taken', function () {
    User::factory()->create(['username' => 'janedoe']);

    $token = 'login_'.Str::random(48);
    Cache::put("tg-login:{$token}", ['user_id' => null], 600);

    $this->postJson(route('telegram.webhook'), telegramUpdate("/start {$token}"));

    $user = User::firstWhere('telegram_id', 555001);

    expect($user->username)->toBeNull()
        ->and($user->telegram_username)->toBe('janedoe');
});

test('webhook ignores expired login tokens', function () {
    $this->postJson(route('telegram.webhook'), telegramUpdate('/start login_missing'))
        ->assertOk();

    expect(User::count())->toBe(0);
});

test('webhook ignores login starts in group chats', function () {
    $token = 'login_'.Str::random(48);
    Cache::put("tg-login:{$token}", ['user_id' => null], 600);

    $update = telegramUpdate("/start {$token}");
    $update['message']['chat']['type'] = 'group';

    $this->postJson(route('telegram.webhook'), $update)->assertOk();

    expect(Cache::get("tg-login:{$token}"))->toBe(['user_id' => null]);
    expect(User::count())->toBe(0);
});

test('webapp signs in a verified mini app user and creates the account', function () {
    $response = $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(),
    ]);

    $response->assertOk()->assertJsonStructure(['redirect']);

    $user = User::firstWhere('telegram_id', 555001);

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->username)->toBe('janedoe')
        ->and($user->telegram_username)->toBe('janedoe')
        ->and($user->telegram_chat_id)->toBeNull();

    $this->assertAuthenticatedAs($user);
});

test('webapp signs in an existing telegram user without duplicating', function () {
    $user = User::factory()->withTelegram()->create(['telegram_id' => 555001]);

    $response = $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(),
    ]);

    $response->assertOk();
    $this->assertAuthenticatedAs($user);

    expect(User::where('telegram_id', 555001)->count())->toBe(1);
});

test('webapp binds the ambassador from a ref start param on sign-in', function () {
    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();

    $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(startParam: "ref_{$ambassador->referral_code}"),
    ])->assertOk();

    $user = User::firstWhere('telegram_id', 555001);

    expect($user->referred_by_id)->toBe($ambassador->id);
});

test('webapp binds an existing telegram member from a ref start param', function () {
    $ambassador = User::factory()->create();
    $ambassador->ensureReferralCode();
    $member = User::factory()->withTelegram()->create(['telegram_id' => 555001]);

    $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(startParam: "ref_{$ambassador->referral_code}"),
    ])->assertOk();

    expect($member->fresh()->referred_by_id)->toBe($ambassador->id);
});

test('webapp ignores unknown and self invite start params', function () {
    $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(startParam: 'ref_nosuchcode'),
    ])->assertOk();

    $user = User::firstWhere('telegram_id', 555001);
    expect($user->referred_by_id)->toBeNull();

    $user->ensureReferralCode();
    $this->post(route('logout'));

    $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(startParam: "ref_{$user->referral_code}"),
    ])->assertOk();

    expect($user->fresh()->referred_by_id)->toBeNull();
});

test('an authenticated mini app session rebinds the referrer without a new login', function () {
    $first = User::factory()->create();
    $first->ensureReferralCode();
    $second = User::factory()->create();
    $second->ensureReferralCode();

    $member = User::factory()->withTelegram()->create([
        'telegram_id' => 555001,
        'referred_by_id' => $first->id,
    ]);

    $this->actingAs($member)
        ->postJson(route('telegram.login.webapp'), [
            'init_data' => signedInitData(startParam: "ref_{$second->referral_code}"),
        ])
        ->assertOk();

    expect($member->fresh()->referred_by_id)->toBe($second->id)
        ->and(auth()->id())->toBe($member->id);
});

test('webapp rejects a tampered signature', function () {
    $initData = signedInitData();
    $initData = str_replace('auth_date=', 'auth_date=1', $initData);

    $this->postJson(route('telegram.login.webapp'), ['init_data' => $initData])
        ->assertStatus(401);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('webapp rejects a stale payload', function () {
    $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(authDate: time() - 90000),
    ])->assertStatus(401);

    $this->assertGuest();
});

test('webapp requires the init data field', function () {
    $this->postJson(route('telegram.login.webapp'))->assertStatus(422);
});

test('webapp fails when telegram is not configured', function () {
    config()->set('services.telegram.bot_token', null);

    $this->postJson(route('telegram.login.webapp'), [
        'init_data' => signedInitData(),
    ])->assertStatus(503);
});

test('debug endpoint records client-reported events', function () {
    Log::spy();

    $this->postJson(route('telegram.login.debug'), [
        'event' => 'webapp-failed',
        'context' => ['status' => 503],
    ])->assertOk()->assertJson(['ok' => true]);

    Log::shouldHaveReceived('info')
        ->with('telegram.webapp.client', [
            'event' => 'webapp-failed',
            'context' => ['status' => 503],
        ]);
});

test('debug endpoint rejects malformed event names', function () {
    $this->postJson(route('telegram.login.debug'), [
        'event' => 'not a valid event!',
    ])->assertStatus(422);
});

test('link token flow still binds telegram chat id and stores identity', function () {
    $user = User::factory()->create(['telegram_link_token' => 'link-token-123']);

    $this->postJson(route('telegram.webhook'), telegramUpdate('/start link-token-123'))
        ->assertOk();

    $user->refresh();

    expect($user->telegram_chat_id)->toBe('12345')
        ->and($user->telegram_link_token)->toBeNull()
        ->and($user->telegram_id)->toBe(555001)
        ->and($user->telegram_username)->toBe('janedoe');
});
