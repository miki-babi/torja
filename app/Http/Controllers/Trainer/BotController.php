<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Services\Trainer\BotConnector;
use App\Support\Telegram\ApiResult;
use App\Support\Telegram\TelegramApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bot onboarding + settings: connect the trainer's Telegram bot token,
 * show live webhook status (ok/mismatch/missing/unreachable), self-repair
 * via reconnect, or disconnect entirely.
 */
class BotController extends Controller
{
    public function __construct(
        private readonly BotConnector $connector,
    ) {}

    /**
     * Bot setup page — connection state plus live getWebhookInfo status.
     */
    public function edit(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        return Inertia::render('trainer/bot-setup', [
            'trainer' => [
                'brand_name' => $trainer->brand_name,
                'mini_app_name' => $trainer->mini_app_name,
                'bot_username' => $trainer->bot_username,
                'connected' => $trainer->hasConnectedBot(),
                'invite_url' => $trainer->miniAppUrl(),
                'expected_webhook' => $trainer->webhookUrl(),
                'webhook_registered' => $trainer->webhook_registered_at !== null,
                'menu_button_set' => $trainer->menu_button_set_at !== null,
            ],
            'webhook' => $this->webhookStatus($trainer),
        ]);
    }

    /**
     * Connect a bot token: validate with getMe, then register webhook +
     * menu button through the connector.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bot_token' => ['required', 'string', 'max:128', 'regex:/^\d{5,}:[\w-]{20,}$/'],
        ]);

        $trainer = $request->user()->ensureTrainer();

        $result = $this->call(
            fn (): ApiResult => $this->connector->connect($trainer, trim($data['bot_token'])),
        );

        Inertia::flash('toast', [
            'type' => $result->ok ? 'success' : 'error',
            'message' => $result->ok
                ? __('Bot connected — your clients can now find you on Telegram.')
                : __('Telegram rejected the bot: :error', ['error' => $result->description]),
        ]);

        return back();
    }

    /**
     * Update branding fields (brand name, BotFather mini-app short name).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:80'],
            'mini_app_name' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z][\w]*$/'],
        ]);

        $trainer = $request->user()->ensureTrainer();

        $trainer->update([
            'brand_name' => $data['brand_name'] ?? null,
            'mini_app_name' => $data['mini_app_name'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Bot settings saved.'),
        ]);

        return back();
    }

    /**
     * Re-register webhook + menu button — self-repair for a mismatched or
     * cleared webhook.
     */
    public function reconnect(Request $request): RedirectResponse
    {
        $trainer = $request->user()->ensureTrainer();

        $result = $this->call(
            fn (): ApiResult => $this->connector->reconnect($trainer),
        );

        Inertia::flash('toast', [
            'type' => $result->ok ? 'success' : 'error',
            'message' => $result->ok
                ? __('Webhook re-registered.')
                : __('Could not re-register the webhook: :error', ['error' => $result->description]),
        ]);

        return back();
    }

    /**
     * Delete the webhook and forget the bot.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $trainer = $request->user()->ensureTrainer();

        $result = $this->call(
            fn (): ApiResult => $this->connector->disconnect($trainer),
        );

        Inertia::flash('toast', [
            'type' => $result->ok ? 'success' : 'error',
            'message' => $result->ok
                ? __('Bot disconnected.')
                : __('Could not disconnect the bot: :error', ['error' => $result->description]),
        ]);

        return back();
    }

    /**
     * Live webhook registration from getWebhookInfo, mapped to a status
     * the page can badge. Null when no bot is connected.
     *
     * @return array{status: string, url?: string, expected_url?: string, pending_update_count?: int, last_error_date?: int|null, last_error_message?: string|null}|null
     */
    private function webhookStatus(Trainer $trainer): ?array
    {
        if (! $trainer->hasConnectedBot()) {
            return null;
        }

        $info = $this->call(
            fn (): ApiResult => TelegramApi::forToken($trainer->bot_token)->getWebhookInfo(),
        );

        if (! $info->ok) {
            return ['status' => 'unreachable'];
        }

        $url = (string) ($info->result['url'] ?? '');
        $expected = $trainer->webhookUrl();

        return [
            'status' => $url === ''
                ? 'missing'
                : ($url === $expected ? 'ok' : 'mismatch'),
            'url' => $url,
            'expected_url' => $expected,
            'pending_update_count' => (int) ($info->result['pending_update_count'] ?? 0),
            'last_error_date' => $info->result['last_error_date'] ?? null,
            'last_error_message' => $info->result['last_error_message'] ?? null,
        ];
    }

    /**
     * Run a Bot API call, converting transport errors into a failed
     * ApiResult so callers get a single failure channel.
     *
     * @param  callable(): ApiResult  $call
     */
    private function call(callable $call): ApiResult
    {
        try {
            return $call();
        } catch (\Throwable $e) {
            report($e);

            return new ApiResult(ok: false, description: $e->getMessage());
        }
    }
}
