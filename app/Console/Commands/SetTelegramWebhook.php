<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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

        $payload = ['url' => $url];
        $secret = config('services.telegram.webhook_secret');

        if (is_string($secret) && $secret !== '') {
            $payload['secret_token'] = $secret;
        }

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

        if (! $response->ok() || ! ($response->json('ok') ?? false)) {
            $this->error('setWebhook failed: '.$response->body());

            return self::FAILURE;
        }

        $this->info("Webhook set to {$url}.");

        return self::SUCCESS;
    }
}
