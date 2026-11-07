<?php

namespace App\Services\Trainer;

use App\Models\Trainer;
use App\Support\Telegram\ApiResult;
use App\Support\Telegram\TelegramApi;
use Illuminate\Support\Str;

/**
 * Connects a trainer's own Telegram bot: validates the token, registers the
 * per-trainer webhook with its own secret, and pins the Mini App menu button
 * so the bot becomes the client-facing surface without any BotFather steps.
 */
class BotConnector
{
    /**
     * Validate a submitted token against getMe, then register the webhook
     * and menu button. Returns the failing ApiResult unchanged so callers
     * can surface Telegram's own error description.
     */
    public function connect(Trainer $trainer, string $token): ApiResult
    {
        $api = TelegramApi::forToken($token);

        $me = $api->getMe();

        if (! $me->ok) {
            return $me;
        }

        $secret = Str::random(48);

        $webhook = $api->setWebhook($trainer->webhookUrl(), $secret);

        if (! $webhook->ok) {
            return $webhook;
        }

        $menu = $api->setChatMenuButton($trainer->miniAppUrl());

        $trainer->forceFill([
            'bot_token' => $token,
            'bot_username' => $me->result['username'] ?? null,
            'webhook_secret' => $secret,
            'webhook_registered_at' => now(),
            'menu_button_set_at' => $menu->ok ? now() : null,
        ])->save();

        return $webhook;
    }

    /**
     * Re-push the webhook (and menu button) for an already-connected bot —
     * the self-repair path when getWebhookInfo reports a mismatch.
     */
    public function reconnect(Trainer $trainer): ApiResult
    {
        if (! $trainer->hasConnectedBot()) {
            return new ApiResult(ok: false, description: 'No bot connected.');
        }

        $api = TelegramApi::forToken($trainer->bot_token);

        $secret = $trainer->webhook_secret ?? Str::random(48);

        $webhook = $api->setWebhook($trainer->webhookUrl(), $secret);

        if (! $webhook->ok) {
            return $webhook;
        }

        $menu = $api->setChatMenuButton($trainer->miniAppUrl());

        $trainer->forceFill([
            'webhook_secret' => $secret,
            'webhook_registered_at' => now(),
            'menu_button_set_at' => $menu->ok ? now() : $trainer->menu_button_set_at,
        ])->save();

        return $webhook;
    }

    /**
     * Remove the webhook and forget the bot entirely so a different token
     * can be connected next.
     */
    public function disconnect(Trainer $trainer): ApiResult
    {
        if (! $trainer->hasConnectedBot()) {
            return new ApiResult(ok: false, description: 'No bot connected.');
        }

        $result = TelegramApi::forToken($trainer->bot_token)->deleteWebhook();

        if (! $result->ok) {
            return $result;
        }

        $trainer->forceFill([
            'bot_token' => null,
            'bot_username' => null,
            'webhook_secret' => null,
            'webhook_registered_at' => null,
            'menu_button_set_at' => null,
        ])->save();

        return $result;
    }
}
