<?php

namespace App\Notifications;

use App\Notifications\Channels\TelegramChannel;
use App\Support\IntentScorer;
use App\Support\Nudges\NudgeRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A lifecycle nudge ("finish the wizard", "your plan is ready", "complete
 * payment") delivered over the database bell, email, and Telegram when the
 * user has linked a chat. Copy is translated per the user's locale and
 * personalized with their declared or predicted goal.
 */
class NudgeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $slug) {}

    /**
     * Delivery channels for this user.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if (! empty($notifiable->telegram_chat_id)) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    /**
     * The localized payload for the notifiable.
     *
     * @return array{slug: string, title: string, body: string, cta: string, url: string}
     */
    private function payload(object $notifiable): array
    {
        $profile = $notifiable->nutritionProfile;
        $goal = $profile?->goal['primary_goal']
            ?? app(IntentScorer::class)->forUser($notifiable)['goal']
            ?? null;
        $stepsLeft = $profile !== null && $profile->status === 'in_progress'
            ? max(0, 13 - (int) $profile->current_step)
            : null;

        return NudgeRegistry::payload($this->slug, $goal, $stepsLeft, $notifiable->locale ?? null);
    }

    /**
     * Mail rendering.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload($notifiable);

        return (new MailMessage)
            ->subject($payload['title'])
            ->line($payload['body'])
            ->action($payload['cta'], $payload['url']);
    }

    /**
     * Telegram rendering: text + inline button.
     *
     * @return array{text: string, cta: string, url: string}
     */
    public function toTelegram(object $notifiable): array
    {
        $payload = $this->payload($notifiable);

        return [
            'text' => $payload['title']."\n\n".$payload['body'],
            'cta' => $payload['cta'],
            'url' => $payload['url'],
        ];
    }

    /**
     * Database bell payload.
     *
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload($notifiable);
    }
}
