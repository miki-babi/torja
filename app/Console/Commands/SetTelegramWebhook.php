<?php

namespace App\Console\Commands;

use App\Support\Telegram\TelegramApi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:set-webhook {--url= : Public webhook URL (defaults to APP_URL/telegram/webhook)}')]
#[Description('Register the Telegram webhook with the Bot API')]
class SetTelegramWebhook extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = config('services.telegram.bot_token');

        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        $url = $this->option('url') ?? rtrim((string) config('app.url'), '/').'/telegram/webhook';

        $result = app(TelegramApi::class)->setWebhook($url);

        if (! $result->ok) {
            $this->error('setWebhook failed: '.$result->description);

            return self::FAILURE;
        }

        $this->info("Webhook set to {$url}.");

        return self::SUCCESS;
    }
}
