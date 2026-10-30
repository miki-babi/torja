<?php

namespace App\Enums;

/**
 * The Bot API message shape a broadcast takes. `text` rides sendMessage,
 * single-media types carry `telegram_text` as the caption, `media_group`
 * sends an album and `poll` builds a sendPoll payload.
 */
enum TelegramBroadcastType: string
{
    case Text = 'text';
    case Photo = 'photo';
    case Video = 'video';
    case Document = 'document';
    case Animation = 'animation';
    case MediaGroup = 'media_group';
    case Poll = 'poll';

    /**
     * Whether the broadcast sends one attached file (caption message types).
     */
    public function isSingleMedia(): bool
    {
        return in_array($this, [self::Photo, self::Video, self::Document, self::Animation], true);
    }

    /**
     * Whether `telegram_text` is sent as a caption (1024 chars) rather than
     * a message body (4096 chars). Polls carry no caption.
     */
    public function usesCaption(): bool
    {
        return $this !== self::Text && $this !== self::Poll;
    }

    /**
     * The Bot API media kind each album item may be.
     *
     * @return array<int, string>
     */
    public static function mediaItemKinds(): array
    {
        return ['photo', 'video'];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
