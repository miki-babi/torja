<?php

namespace App\Support\Telegram;

use App\Enums\TelegramBroadcastType;
use App\Models\TelegramBroadcast;
use Illuminate\Support\Facades\Storage;

/**
 * Sends a broadcast's payload to one chat. Resolves media references —
 * local uploads on the `public` disk go out multipart, URLs and cached
 * file_ids go out as strings — and writes any file_ids Telegram returns
 * back onto the broadcast so later sends (and recurrence runs) reuse
 * them instead of re-uploading.
 */
class BroadcastSender
{
    /**
     * Send to a chat id; the caller handles delivery bookkeeping.
     */
    public function sendToChat(TelegramBroadcast $broadcast, string|int $chatId): ApiResult
    {
        $api = app(TelegramApi::class);
        $markup = BroadcastKeyboard::markup($broadcast->buttons, $broadcast->id);
        $flags = $broadcast->sendFlags();
        $media = $broadcast->media ?? [];

        $result = match ($broadcast->type) {
            TelegramBroadcastType::Text => $api->sendMessage(
                $chatId, $broadcast->telegram_text ?? '', $markup, $flags,
            ),
            TelegramBroadcastType::Photo => $this->sendSingle($api, $broadcast, $media, 'sendPhoto', 'photo', $chatId, $markup, $flags),
            TelegramBroadcastType::Video => $this->sendSingle($api, $broadcast, $media, 'sendVideo', 'video', $chatId, $markup, $flags),
            TelegramBroadcastType::Document => $this->sendSingle($api, $broadcast, $media, 'sendDocument', 'document', $chatId, $markup, $flags),
            TelegramBroadcastType::Animation => $this->sendSingle($api, $broadcast, $media, 'sendAnimation', 'animation', $chatId, $markup, $flags),
            TelegramBroadcastType::MediaGroup => $this->sendAlbum($api, $broadcast, $media, $chatId, $markup, $flags),
            TelegramBroadcastType::Poll => $api->sendPoll(
                $chatId, $broadcast->poll ?? [], $markup, $flags,
            ),
        };

        if ($result->ok && $result->fileIds !== []) {
            $this->cacheFileIds($broadcast, $result->fileIds);
        }

        return $result;
    }

    /**
     * One media item with the broadcast caption.
     *
     * @param  array<int, array<string, mixed>>  $media
     * @param  array<string, mixed>|null  $markup
     * @param  array<string, bool>  $flags
     */
    private function sendSingle(
        TelegramApi $api,
        TelegramBroadcast $broadcast,
        array $media,
        string $method,
        string $mediaKey,
        string|int $chatId,
        ?array $markup,
        array $flags,
    ): ApiResult {
        $item = $media[0] ?? [];

        return $api->sendMedia(
            $method,
            $chatId,
            $mediaKey,
            $this->mediaRef($item),
            $broadcast->telegram_text,
            $markup,
            $flags,
        );
    }

    /**
     * An album — each item carries its own converted caption (empty
     * captions are omitted from the payload by the API client).
     *
     * @param  array<int, array<string, mixed>>  $media
     * @param  array<string, mixed>|null  $markup
     * @param  array<string, bool>  $flags
     */
    private function sendAlbum(
        TelegramApi $api,
        TelegramBroadcast $broadcast,
        array $media,
        string|int $chatId,
        ?array $markup,
        array $flags,
    ): ApiResult {
        $items = array_map(fn (array $item): array => [
            'type' => (string) ($item['kind'] ?? 'photo'),
            'media' => $this->mediaRef($item),
            'caption' => (string) ($item['caption_telegram'] ?? ''),
        ], $media);

        return $api->sendMediaGroup($chatId, $items, $flags, $markup);
    }

    /**
     * Resolve a stored media item to a sendable reference: a cached
     * file_id beats everything, then URL, then a `public`-disk path.
     *
     * @param  array<string, mixed>  $item
     */
    private function mediaRef(array $item): string
    {
        $fileId = $item['file_id'] ?? null;

        if (is_string($fileId) && $fileId !== '') {
            return $fileId;
        }

        $source = $item['source'] ?? 'url';
        $value = (string) ($item['value'] ?? '');

        if ($source === 'path') {
            return Storage::disk('public')->path($value);
        }

        return $value;
    }

    /**
     * Persist returned file_ids back onto `media` items in order so the
     * next send skips the upload entirely.
     *
     * @param  array<int, string>  $fileIds
     */
    private function cacheFileIds(TelegramBroadcast $broadcast, array $fileIds): void
    {
        $media = $broadcast->media ?? [];

        if (count($fileIds) !== count($media)) {
            return;
        }

        foreach ($fileIds as $i => $fileId) {
            $media[$i]['file_id'] = $fileId;
        }

        $broadcast->forceFill(['media' => $media])->save();
    }
}
