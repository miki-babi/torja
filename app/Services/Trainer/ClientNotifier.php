<?php

namespace App\Services\Trainer;

use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Trainer;
use App\Models\User;
use App\Support\Telegram\TelegramApi;

/**
 * Direct messages a client through their trainer's own bot — e.g. when a
 * plan is assigned to them. No-ops when the bot is disconnected or the
 * client is not reachable on Telegram.
 */
class ClientNotifier
{
    /**
     * Tell the client a new plan was assigned, with a button that opens the
     * trainer's mini app.
     */
    public function planAssigned(Trainer $trainer, User $client, ExerciseProgram|MealProgram $program): void
    {
        $coach = e($trainer->display_name);
        $name = e($program->name);

        $this->notify(
            $trainer,
            $client,
            "💪 New plan from {$coach}: <b>{$name}</b>\n\nTap below to open it in the app.",
        );
    }

    /**
     * Send an arbitrary reminder DM through the trainer's bot, with a
     * button that opens the mini app. No-ops when the bot is disconnected
     * or the client is not reachable on Telegram.
     */
    public function notify(Trainer $trainer, User $client, string $html): void
    {
        if (! $trainer->hasConnectedBot()) {
            return;
        }

        $chatId = $client->telegramRecipientId();

        if ($chatId === null) {
            return;
        }

        TelegramApi::forToken($trainer->bot_token)->sendMessage(
            $chatId,
            $html,
            ['inline_keyboard' => [[
                [
                    'text' => 'Open app',
                    'web_app' => ['url' => $trainer->miniAppUrl()],
                ],
            ]]],
        );
    }
}
