<?php

namespace App\Enums;

/**
 * Broadcast lifecycle. `scheduled` covers both one-off future sends and
 * the resting state of a recurring broadcast between runs. `sending` is
 * set while a run's deliveries are being worked off the queue.
 */
enum TelegramBroadcastStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    /**
     * Whether the composer may still edit the broadcast.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Scheduled, self::Paused], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
