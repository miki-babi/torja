<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Telegram\UserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Receives Telegram bot updates. Two `/start` flows are served:
 *
 * - `login_<token>` — browser sign-in: binds the pending token minted by
 *   TelegramLoginController to the Telegram user's account (created on the
 *   fly when unknown), so the polling login page can open a session.
 * - `<token>` — account linking: matches `telegram_link_token` generated on
 *   the member profile page and binds `telegram_chat_id` for reminders.
 */
class WebhookController extends Controller
{
    /**
     * Handle an incoming update.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');

        if (is_string($secret) && $secret !== ''
            && ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if (! is_string($text) || ! str_starts_with($text, '/start') || $chatId === null) {
            return response()->json(['ok' => true]);
        }

        $token = trim(substr($text, strlen('/start')));

        if (str_starts_with($token, 'login_')) {
            $this->handleLogin($message, $chatId, $token);
        } else {
            $this->handleLink($message, $chatId, $token);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Bind a browser login token to the sender's account.
     *
     * @param  array<string, mixed>  $message
     */
    private function handleLogin(array $message, mixed $chatId, string $token): void
    {
        if (($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $key = "tg-login:{$token}";
        $entry = Cache::get($key);

        if (! is_array($entry)) {
            $this->reply($chatId, 'This login link has expired. Go back to '.config('app.name').' and start again.');

            return;
        }

        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        $resolver = app(UserResolver::class);

        if ($resolver->telegramId($from) === null) {
            return;
        }

        $user = $resolver->resolve($from, (string) $chatId);

        $entry['user_id'] = $user->id;
        Cache::put($key, $entry, now()->addMinutes(10));

        $this->reply($chatId, "You're signed in — return to your browser to continue.");
    }

    /**
     * Bind a profile link token to the sender's chat.
     *
     * @param  array<string, mixed>  $message
     */
    private function handleLink(array $message, mixed $chatId, string $token): void
    {
        $user = $token === ''
            ? null
            : User::query()->where('telegram_link_token', $token)->first();

        if ($user !== null) {
            $from = is_array($message['from'] ?? null) ? $message['from'] : [];
            $resolver = app(UserResolver::class);
            $telegramId = $resolver->telegramId($from);
            $telegramUsername = $resolver->telegramUsername($from);

            // Never wipe existing values when the update lacks a `from`
            // object, and never write a value another account already holds.
            $user->update([
                'telegram_chat_id' => (string) $chatId,
                'telegram_link_token' => null,
                'telegram_id' => $telegramId === null || User::query()
                    ->where('telegram_id', $telegramId)
                    ->where('id', '!=', $user->id)
                    ->exists()
                        ? $user->telegram_id
                        : $telegramId,
                'telegram_username' => $telegramUsername === null || User::query()
                    ->where('telegram_username', $telegramUsername)
                    ->where('id', '!=', $user->id)
                    ->exists()
                        ? $user->telegram_username
                        : $telegramUsername,
            ]);

            $this->reply($chatId, 'Connected — you will now receive reminders from '.config('app.name').' here.');
        } else {
            $this->reply($chatId, 'This link has expired. Open your TrainPro profile and tap "Connect Telegram" again.');
        }
    }

    /**
     * Post a confirmation message back to the chat.
     */
    private function reply(mixed $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');

        if (empty($token)) {
            return;
        }

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
