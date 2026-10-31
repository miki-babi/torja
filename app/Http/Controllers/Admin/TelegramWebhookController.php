<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Telegram\ApiResult;
use App\Support\Telegram\TelegramApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Telegram webhook management: getMe/getWebhookInfo drive the status
 * card, and set/delete register or remove the webhook with the Bot API.
 * The secret token stays in env (TELEGRAM_WEBHOOK_SECRET) — it's sent
 * with every setWebhook call but never rendered to the page.
 */
class TelegramWebhookController extends Controller
{
    /**
     * Webhook status: bot identity plus the registration Telegram holds.
     */
    public function show(TelegramApi $api): Response
    {
        $configured = filled(config('services.telegram.bot_token'));

        $bot = null;
        $info = null;
        $apiError = null;

        if ($configured) {
            [$bot, $info, $apiError] = $this->fetchStatus($api);
        }

        return Inertia::render('admin/telegram/webhook', [
            'configured' => $configured,
            'bot' => $bot,
            'info' => $info,
            'expectedUrl' => route('telegram.webhook'),
            'secretConfigured' => filled(config('services.telegram.webhook_secret')),
            'apiError' => $apiError,
        ]);
    }

    /**
     * Register (or re-point) the webhook. Empty URL means the app's own
     * endpoint. Telegram requires HTTPS — it rejects anything else with
     * an error we surface.
     */
    public function update(Request $request, TelegramApi $api): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['nullable', 'url', 'starts_with:https://', 'max:255'],
        ]);

        $result = $this->call(
            fn (): ApiResult => $api->setWebhook($validated['url'] ?? route('telegram.webhook')),
        );

        Inertia::flash('toast', [
            'type' => $result->ok ? 'success' : 'error',
            'message' => $result->ok
                ? __('Webhook registered.')
                : __('Telegram rejected the webhook: :error', ['error' => $result->description]),
        ]);

        return back();
    }

    /**
     * Remove the webhook so the bot stops receiving updates here.
     */
    public function destroy(Request $request, TelegramApi $api): RedirectResponse
    {
        $result = $this->call(
            fn (): ApiResult => $api->deleteWebhook($request->boolean('drop_pending_updates')),
        );

        Inertia::flash('toast', [
            'type' => $result->ok ? 'success' : 'error',
            'message' => $result->ok
                ? __('Webhook deleted — the bot no longer receives updates.')
                : __('Could not delete the webhook: :error', ['error' => $result->description]),
        ]);

        return back();
    }

    /**
     * Fetch bot identity and webhook registration, tolerating API and
     * network failures so the status page always renders.
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null, 2: string|null}
     */
    private function fetchStatus(TelegramApi $api): array
    {
        $bot = null;
        $info = null;
        $apiError = null;

        $me = $this->call(fn (): ApiResult => $api->getMe());

        if ($me->ok) {
            $bot = [
                'id' => $me->result['id'] ?? null,
                'username' => $me->result['username'] ?? null,
                'name' => $me->result['first_name'] ?? null,
            ];
        } else {
            $apiError = $me->description;
        }

        $webhook = $this->call(fn (): ApiResult => $api->getWebhookInfo());

        if ($webhook->ok) {
            $result = $webhook->result;

            $info = [
                'url' => $result['url'] ?? '',
                'has_custom_certificate' => (bool) ($result['has_custom_certificate'] ?? false),
                'pending_update_count' => (int) ($result['pending_update_count'] ?? 0),
                'ip_address' => $result['ip_address'] ?? null,
                'last_error_date' => $result['last_error_date'] ?? null,
                'last_error_message' => $result['last_error_message'] ?? null,
                'last_synchronization_error_date' => $result['last_synchronization_error_date'] ?? null,
                'max_connections' => $result['max_connections'] ?? null,
                'allowed_updates' => $result['allowed_updates'] ?? [],
            ];
        } else {
            $apiError ??= $webhook->description;
        }

        return [$bot, $info, $apiError];
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
