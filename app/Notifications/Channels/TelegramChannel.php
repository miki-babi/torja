<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/**
 * Sends notifications through the Telegram Bot API when the notifiable has
 * a linked `telegram_chat_id`. The notification must implement
 * `toTelegram()` returning `{text, cta, url}`.
 */
class TelegramChannel
{
    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = $notifiable->telegram_chat_id ?? null;
        $token = config('services.telegram.bot_token');

        if (empty($chatId) || empty($token) || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        /** @var array{text: string, cta: string, url: string} $message */
        $message = $notification->toTelegram($notifiable);

        Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message['text'],
            'reply_markup' => [
                'inline_keyboard' => [[['text' => $message['cta'], 'url' => $message['url']]]],
            ],
        ])->throw();
    }
}
