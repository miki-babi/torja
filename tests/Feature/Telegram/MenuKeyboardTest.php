<?php

use App\Models\User;
use App\Support\Telegram\MenuKeyboard;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram.bot_username', 'trainpro_bot');
    config()->set('services.telegram.bot_token', 'test-token');
    config()->set('services.telegram.mini_app_url', null);

    Http::fake();
});

function menuUpdate(string $text, string $chatType = 'private'): array
{
    return [
        'message' => [
            'chat' => ['id' => 12345, 'type' => $chatType],
            'text' => $text,
            'from' => ['id' => 555001, 'username' => 'janedoe'],
        ],
    ];
}

test('bot replies carry the persistent menu keyboard', function () {
    User::factory()->create(['telegram_link_token' => 'link-token-123']);

    $this->postJson(route('telegram.webhook'), menuUpdate('/start link-token-123'))
        ->assertOk();

    Http::assertSent(
        fn ($request) => $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && isset($request['reply_markup']['keyboard'][0][0]['text'])
    );
});

test('a bare start greets with the menu instead of an expired link', function () {
    $this->postJson(route('telegram.webhook'), menuUpdate('/start'))
        ->assertOk();

    Http::assertSent(
        fn ($request) => $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && str_starts_with((string) $request['text'], 'Welcome to '.config('app.name'))
            && ! str_contains((string) $request['text'], 'expired')
            && isset($request['reply_markup']['keyboard'][0][0]['text'])
    );
});

test('link starts in group chats are ignored', function () {
    $user = User::factory()->create(['telegram_link_token' => 'link-token-123']);

    $this->postJson(route('telegram.webhook'), menuUpdate('/start link-token-123', 'group'))
        ->assertOk();

    Http::assertSentCount(0);

    expect($user->fresh()->telegram_chat_id)->toBeNull()
        ->and($user->fresh()->telegram_link_token)->toBe('link-token-123');
});

test('unknown link tokens still report an expired link', function () {
    $this->postJson(route('telegram.webhook'), menuUpdate('/start bogus-token'))
        ->assertOk();

    Http::assertSent(
        fn ($request) => str_contains((string) $request['text'], 'This link has expired')
            && str_contains((string) $request['text'], (string) config('app.name'))
    );
});

test('open app button replies with an inline mini app button', function () {
    $this->postJson(route('telegram.webhook'), menuUpdate('🚀 Open App'))
        ->assertOk();

    Http::assertSent(
        fn ($request) => ($request['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null) === rtrim(config('app.url'), '/').'/'
            && isset($request['reply_markup']['inline_keyboard'][0][0]['text'])
            && ! isset($request['reply_markup']['keyboard'])
    );
});

test('quick action buttons deep link into the app', function () {
    $this->postJson(route('telegram.webhook'), menuUpdate('🏋️ Today\'s workout'))
        ->assertOk();
    $this->postJson(route('telegram.webhook'), menuUpdate('👥 Invite friends'))
        ->assertOk();

    Http::assertSent(
        fn ($request) => ($request['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? '') === rtrim(config('app.url'), '/').'/today'
    );
    Http::assertSent(
        fn ($request) => ($request['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? '') === rtrim(config('app.url'), '/').'/ambassador'
    );
});

test('web_app buttons use the configured mini app url', function () {
    config()->set('services.telegram.mini_app_url', 'https://app.example.com/');

    $this->postJson(route('telegram.webhook'), menuUpdate('🚀 Open App'))
        ->assertOk();

    Http::assertSent(
        fn ($request) => ($request['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null) === 'https://app.example.com/'
    );
});

test('menu taps in group chats are ignored', function () {
    $this->postJson(route('telegram.webhook'), menuUpdate('🚀 Open App', 'group'))
        ->assertOk();

    Http::assertSentCount(0);
});

test('unknown texts get no reply', function () {
    $this->postJson(route('telegram.webhook'), menuUpdate('hello there'))
        ->assertOk();

    Http::assertSentCount(0);
});

test('the menu keyboard includes a share location button', function () {
    $markup = app(MenuKeyboard::class)->markup();

    $buttons = collect($markup['keyboard'])->flatten(1);

    expect($buttons->contains(
        fn ($button) => ($button['request_location'] ?? false) === true,
    ))->toBeTrue();
});

test('a shared location pins the member\'s coordinates', function () {
    $user = User::factory()->create(['telegram_id' => 555001]);

    $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 12345, 'type' => 'private'],
            'from' => ['id' => 555001, 'username' => 'janedoe'],
            'location' => ['latitude' => 9.0301, 'longitude' => 38.7654],
        ],
    ])->assertOk();

    expect($user->refresh()->latitude)->toBe(9.0301)
        ->and($user->longitude)->toBe(38.7654)
        ->and($user->location_shared_at)->not->toBeNull();

    Http::assertSent(
        fn ($request) => str_contains((string) $request['text'], 'Location saved'),
    );
});

test('a location share from an unlinked user asks to connect first', function () {
    $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 12345, 'type' => 'private'],
            'from' => ['id' => 999999],
            'location' => ['latitude' => 9.0301, 'longitude' => 38.7654],
        ],
    ])->assertOk();

    expect(User::query()->where('telegram_id', 999999)->exists())->toBeFalse();

    Http::assertSent(
        fn ($request) => str_contains((string) $request['text'], 'Connect your'),
    );
});

test('location shares in group chats are ignored', function () {
    $user = User::factory()->create(['telegram_id' => 555001]);

    $this->postJson(route('telegram.webhook'), [
        'message' => [
            'chat' => ['id' => 12345, 'type' => 'group'],
            'from' => ['id' => 555001],
            'location' => ['latitude' => 9.0301, 'longitude' => 38.7654],
        ],
    ])->assertOk();

    expect($user->fresh()->latitude)->toBeNull();

    Http::assertSentCount(0);
});
