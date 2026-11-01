<?php

use App\Models\User;
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
