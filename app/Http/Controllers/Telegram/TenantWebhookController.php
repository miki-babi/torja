<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Trainer;
use App\Support\Telegram\TelegramApi;
use App\Support\Telegram\UserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives updates for a trainer's own bot. Per-trainer `webhook_secret`
 * verifies deliveries; the pipeline is deliberately smaller than the
 * platform bot's — `/start` links the sender as a client (account created
 * on the fly) and answers with the Mini App button, every other private
 * text gets the same launch prompt.
 */
class TenantWebhookController extends Controller
{
    /**
     * Handle an incoming update.
     */
    public function __invoke(Request $request, Trainer $trainer): JsonResponse
    {
        if (! $trainer->hasConnectedBot()
            || $trainer->webhook_secret === null
            || ! hash_equals($trainer->webhook_secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;

        if ($chatId === null) {
            return response()->json(['ok' => true]);
        }

        $text = $message['text'] ?? '';

        if (! is_string($text) || ($message['chat']['type'] ?? null) !== 'private') {
            return response()->json(['ok' => true]);
        }

        if (str_starts_with($text, '/start')) {
            $this->handleStart($trainer, $message, $chatId);
        } else {
            $this->sendLaunchPrompt($trainer, $chatId, 'Tap below to open your training app.');
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Link the sender as a client (creating their account when unknown)
     * and greet them with the app button.
     *
     * @param  array<string, mixed>  $message
     */
    private function handleStart(Trainer $trainer, array $message, mixed $chatId): void
    {
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        $user = app(UserResolver::class)->resolve($from, (string) $chatId);

        $trainer->clients()->syncWithoutDetaching([$user->id]);
        $trainer->markClientOpened($user);

        $name = e($trainer->display_name);

        $this->sendLaunchPrompt(
            $trainer,
            $chatId,
            "👋 Welcome to <b>{$name}</b>! Tap below to open your training app.",
        );
    }

    /**
     * Send a message carrying an inline Mini App button.
     */
    private function sendLaunchPrompt(Trainer $trainer, mixed $chatId, string $text): void
    {
        TelegramApi::forToken($trainer->bot_token)->sendMessage(
            $chatId,
            $text,
            ['inline_keyboard' => [[
                [
                    'text' => 'Open app',
                    'web_app' => ['url' => $trainer->miniAppUrl()],
                ],
            ]]],
        );
    }
}
