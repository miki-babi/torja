<?php

namespace App\Support\Telegram;

use App\Models\TelegramBroadcast;
use App\Models\User;
use App\Support\Tracking\Tracker;

/**
 * Handles `callback_query` updates produced by broadcast inline buttons.
 * The callback_data coordinate (`bc.{broadcast}.{row}.{col}`) resolves to
 * the stored button config, whose `action` picks a handler:
 *
 * - `toast` — answer with a popup toast (or modal alert).
 * - `track` — record a `broadcast.button_clicked` event, plus an
 *   optional toast, so button engagement joins the funnel analytics.
 */
class BroadcastCallbacks
{
    /**
     * Dispatch a callback_query update. Always answers the query so the
     * user's client stops showing the loading state.
     *
     * @param  array<string, mixed>  $callbackQuery
     */
    public function handle(array $callbackQuery): void
    {
        $api = app(TelegramApi::class);
        $queryId = (string) ($callbackQuery['id'] ?? '');

        if ($queryId === '') {
            return;
        }

        $data = (string) ($callbackQuery['data'] ?? '');

        if (! preg_match('/^'.preg_quote(BroadcastKeyboard::CALLBACK_PREFIX, '/').'(\d+)\.(\d+)\.(\d+)$/', $data, $m)) {
            $api->answerCallbackQuery($queryId);

            return;
        }

        $broadcast = TelegramBroadcast::query()->find((int) $m[1]);
        $button = $broadcast !== null
            ? BroadcastKeyboard::buttonAt($broadcast->buttons, (int) $m[2], (int) $m[3])
            : null;

        if ($broadcast === null || $button === null) {
            $api->answerCallbackQuery($queryId, 'This button is no longer active.');

            return;
        }

        $user = $this->resolveUser($callbackQuery);
        $toast = $this->toast($button);
        $alert = (bool) ($button['show_alert'] ?? false);

        if (($button['action'] ?? 'toast') === 'track' && $user !== null) {
            app(Tracker::class)->record('broadcast.button_clicked', $user, [
                'broadcast' => $broadcast->id,
                'button' => $button['text'] ?? null,
                'position' => $m[2].'.'.$m[3],
            ]);
        }

        $api->answerCallbackQuery($queryId, $toast, $alert);
    }

    /**
     * The toast text for a callback button — its configured message, or a
     * generic ack for silent trackers.
     *
     * @param  array<string, mixed>  $button
     */
    private function toast(array $button): ?string
    {
        $toast = trim((string) ($button['toast'] ?? ''));

        return $toast !== '' ? mb_substr($toast, 0, 200) : null;
    }

    /**
     * Map the callback's `from` to a local account via telegram_id.
     *
     * @param  array<string, mixed>  $callbackQuery
     */
    private function resolveUser(array $callbackQuery): ?User
    {
        $from = $callbackQuery['from'] ?? [];

        if (! is_array($from)) {
            return null;
        }

        $telegramId = app(UserResolver::class)->telegramId($from);

        return $telegramId === null
            ? null
            : User::query()->where('telegram_id', $telegramId)->first();
    }
}
