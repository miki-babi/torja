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
        if (! $trainer->hasConnectedBot()) {
            return;
        }

        $chatId = $client->telegramRecipientId();

        if ($chatId === null) {
            return;
        }

        $coach = e($trainer->display_name);
        $name = e($program->name);

        TelegramApi::forToken($trainer->bot_token)->sendMessage(
            $chatId,
            "💪 New plan from {$coach}: <b>{$name}</b>\n\nTap below to open it in the app.",
            ['inline_keyboard' => [[
                [
                    'text' => 'Open app',
                    'web_app' => ['url' => $trainer->miniAppUrl()],
                ],
            ]]],
        );
    }
}
