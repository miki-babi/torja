<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    config()->set('services.telegram.bot_token', 'test-token');
});

test('non-admins cannot manage the telegram webhook', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.telegram.webhook.show'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.telegram.webhook.update'))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.telegram.webhook.destroy'))->assertForbidden();
});

test('the status page renders bot identity and webhook info', function () {
    Http::fake([
        '*getMe' => Http::response([
            'ok' => true,
            'result' => ['id' => 7, 'username' => 'trainpro_bot', 'first_name' => 'TrainPro'],
        ]),
        '*getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://app.test/telegram/webhook',
                'pending_update_count' => 3,
                'allowed_updates' => ['message', 'callback_query'],
                'last_error_message' => 'Wrong response from the webhook: 500',
                'last_error_date' => 1730000000,
            ],
        ]),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.telegram.webhook.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/telegram/webhook')
            ->where('configured', true)
            ->where('bot.username', 'trainpro_bot')
            ->where('info.url', 'https://app.test/telegram/webhook')
            ->where('info.pending_update_count', 3)
            ->where('apiError', null));
});

test('the status page degrades gracefully when the api fails', function () {
    Http::fake(['*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

    $this->actingAs($this->admin)
        ->get(route('admin.telegram.webhook.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('bot', null)
            ->where('info', null)
            ->where('apiError', 'Unauthorized'));
});

test('the status page does not call telegram without a bot token', function () {
    config()->set('services.telegram.bot_token', null);
    Http::fake();

    $this->actingAs($this->admin)
        ->get(route('admin.telegram.webhook.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('configured', false)
            ->where('bot', null)
            ->where('info', null));

    Http::assertNotSent(
        fn ($request) => str_contains($request->url(), 'api.telegram.org'),
    );
});

test('setting the webhook posts url, allowed updates and the secret to telegram', function () {
    config()->set('services.telegram.webhook_secret', 'sekrit');
    Http::fake(['*setWebhook*' => Http::response(['ok' => true, 'result' => true])]);

    $this->actingAs($this->admin)
        ->put(route('admin.telegram.webhook.update'), [
            'url' => 'https://app.test/telegram/webhook',
        ])
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook')
        && $request['url'] === 'https://app.test/telegram/webhook'
        && $request['secret_token'] === 'sekrit'
        && $request['allowed_updates'] === ['message', 'callback_query']);
});

test('an empty url falls back to the app webhook endpoint', function () {
    Http::fake(['*setWebhook*' => Http::response(['ok' => true, 'result' => true])]);

    $this->actingAs($this->admin)
        ->put(route('admin.telegram.webhook.update'), ['url' => ''])
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'setWebhook')
        && $request['url'] === route('telegram.webhook'));
});

test('telegram errors surface on set', function () {
    Http::fake(['*setWebhook*' => Http::response([
        'ok' => false,
        'description' => 'Bad webhook: HTTPS url must be provided',
    ], 400)]);

    $this->actingAs($this->admin)
        ->put(route('admin.telegram.webhook.update'), [
            'url' => 'https://app.test/telegram/webhook',
        ])
        ->assertRedirect();
});

test('deleting the webhook calls deleteWebhook with drop pending flag', function () {
    Http::fake(['*deleteWebhook*' => Http::response(['ok' => true, 'result' => true])]);

    $this->actingAs($this->admin)
        ->delete(route('admin.telegram.webhook.destroy'), ['drop_pending_updates' => true])
        ->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteWebhook')
        && $request['drop_pending_updates'] === true);
});
