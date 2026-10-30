<?php

namespace App\Support\Telegram;

use App\Enums\TelegramButtonKind;

/**
 * Turns the stored button grid — rows of composer button config — into
 * the `reply_markup.inline_keyboard` payload for Bot API sends.
 *
 * Callback buttons encode their position as `bc.{broadcastId}.{row}.{col}`
 * (Telegram's 64-byte callback_data budget fits this comfortably); the
 * webhook resolves the action back out of the broadcast's stored config.
 */
class BroadcastKeyboard
{
    public const CALLBACK_PREFIX = 'bc.';

    /**
     * Build the reply_markup array, or null when there are no buttons.
     *
     * @param  array<int, array<int, array<string, mixed>>>|null  $rows
     * @return array{inline_keyboard: array<int, array<int, array<string, mixed>>>}|null
     */
    public static function markup(?array $rows, int $broadcastId): ?array
    {
        if ($rows === null || $rows === []) {
            return null;
        }

        $keyboard = [];

        foreach (array_values($rows) as $r => $row) {
            $buttons = [];

            foreach (array_values($row) as $c => $button) {
                $built = self::button($button, $broadcastId, $r, $c);

                if ($built !== null) {
                    $buttons[] = $built;
                }
            }

            if ($buttons !== []) {
                $keyboard[] = $buttons;
            }
        }

        return $keyboard === [] ? null : ['inline_keyboard' => $keyboard];
    }

    /**
     * The button at a callback_data coordinate, for webhook resolution.
     *
     * @param  array<int, array<int, array<string, mixed>>>|null  $rows
     * @return array<string, mixed>|null
     */
    public static function buttonAt(?array $rows, int $row, int $col): ?array
    {
        return $rows[$row][$col] ?? null;
    }

    /**
     * One InlineKeyboardButton entry from composer config.
     *
     * @param  array<string, mixed>  $button
     * @return array<string, mixed>|null
     */
    private static function button(array $button, int $broadcastId, int $row, int $col): ?array
    {
        $text = trim((string) ($button['text'] ?? ''));
        $kind = TelegramButtonKind::tryFrom((string) ($button['kind'] ?? ''));

        if ($text === '' || $kind === null) {
            return null;
        }

        $base = ['text' => $text];
        $url = trim((string) ($button['url'] ?? ''));
        $query = (string) ($button['query'] ?? '');

        return $base + match ($kind) {
            TelegramButtonKind::Url => ['url' => $url],
            TelegramButtonKind::WebApp => ['web_app' => ['url' => $url]],
            TelegramButtonKind::Callback => [
                'callback_data' => self::CALLBACK_PREFIX."{$broadcastId}.{$row}.{$col}",
            ],
            TelegramButtonKind::LoginUrl => [
                'login_url' => array_filter([
                    'url' => $url,
                    'forward_text' => $button['forward_text'] ?? null,
                    'request_write_access' => ($button['request_write_access'] ?? false) ?: null,
                ], fn (mixed $v): bool => $v !== null),
            ],
            TelegramButtonKind::SwitchInlineQuery => ['switch_inline_query' => $query],
            TelegramButtonKind::SwitchInlineQueryCurrentChat => ['switch_inline_query_current_chat' => $query],
            TelegramButtonKind::SwitchInlineQueryChosenChat => [
                'switch_inline_query_chosen_chat' => array_filter([
                    'query' => $query,
                    'allow_user_chats' => ($button['allow_user_chats'] ?? false) ?: null,
                    'allow_bot_chats' => ($button['allow_bot_chats'] ?? false) ?: null,
                    'allow_group_chats' => ($button['allow_group_chats'] ?? false) ?: null,
                    'allow_channel_chats' => ($button['allow_channel_chats'] ?? false) ?: null,
                ], fn (mixed $v): bool => $v !== null),
            ],
            TelegramButtonKind::CopyText => [
                'copy_text' => ['text' => (string) ($button['copy_text'] ?? '')],
            ],
            TelegramButtonKind::Pay => ['pay' => true],
        };
    }
}
