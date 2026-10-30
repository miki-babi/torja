<?php

namespace App\Support\Telegram;

use Illuminate\Support\Facades\Http;

/**
 * Thin Bot API client used by broadcasts and the webhook. Every call is
 * normalized into an {@see ApiResult}: message ids and file ids are pulled
 * out of successful payloads (albums yield several of each), and failures
 * expose `error_code`, `description` and `retry_after` so callers can
 * distinguish blocked users, rate limits, and real errors.
 */
class TelegramApi
{
    private const BASE = 'https://api.telegram.org';

    /**
     * sendMessage — HTML body plus inline keyboard.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @param  array<string, bool>  $flags
     */
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null, array $flags = []): ApiResult
    {
        return $this->post('sendMessage', $this->withMarkup([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $replyMarkup) + $flags);
    }

    /**
     * Captioned media send — sendPhoto/sendVideo/sendDocument/sendAnimation.
     * Media is a public URL, a cached file_id, or a local path uploaded
     * multipart (attachments count toward Telegram's 50MB upload limit).
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @param  array<string, bool>  $flags
     */
    public function sendMedia(string $method, int|string $chatId, string $mediaKey, string $media, ?string $caption, ?array $replyMarkup = null, array $flags = []): ApiResult
    {
        $payload = $this->withMarkup([
            'chat_id' => $chatId,
            'caption' => $caption ?? '',
            'parse_mode' => 'HTML',
        ], $replyMarkup) + $flags;

        if ($this->isUpload($media)) {
            return $this->postUpload($method, $payload, $mediaKey, $media);
        }

        return $this->post($method, $payload + [$mediaKey => $media]);
    }

    /**
     * sendMediaGroup — 2-10 photo/video items. Each item is
     * `{type, media, caption}` where `media` is a URL, file_id, or local
     * path (uploaded as an attach:// multipart part).
     *
     * @param  array<int, array{type: string, media: string, caption?: string}>  $items
     * @param  array<string, bool>  $flags
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMediaGroup(int|string $chatId, array $items, array $flags = [], ?array $replyMarkup = null): ApiResult
    {
        $uploads = [];
        $media = [];

        foreach ($items as $index => $item) {
            $entry = [
                'type' => $item['type'],
                'parse_mode' => 'HTML',
            ];

            if (isset($item['caption']) && $item['caption'] !== '') {
                $entry['caption'] = $item['caption'];
            }

            if ($this->isUpload($item['media'])) {
                $name = "file{$index}";
                $uploads[$name] = $item['media'];
                $entry['media'] = "attach://{$name}";
            } else {
                $entry['media'] = $item['media'];
            }

            $media[] = $entry;
        }

        $payload = $this->withMarkup(
            ['chat_id' => $chatId, 'media' => $media],
            $replyMarkup,
        ) + $flags;

        if ($uploads !== []) {
            return $this->postUpload('sendMediaGroup', $payload, null, null, $uploads);
        }

        return $this->post('sendMediaGroup', $payload);
    }

    /**
     * sendPoll — regular or quiz, optionally anonymous.
     *
     * @param  array<string, mixed>  $poll
     * @param  array<string, mixed>|null  $replyMarkup
     * @param  array<string, bool>  $flags
     */
    public function sendPoll(int|string $chatId, array $poll, ?array $replyMarkup = null, array $flags = []): ApiResult
    {
        $options = array_map(
            fn (string $option): array => ['text' => $option],
            $poll['options'] ?? [],
        );

        $payload = $this->withMarkup([
            'chat_id' => $chatId,
            'question' => $poll['question'],
            'options' => $options,
            'is_anonymous' => (bool) ($poll['is_anonymous'] ?? true),
            'type' => $poll['type'] ?? 'regular',
            'allows_multiple_answers' => (bool) ($poll['allows_multiple_answers'] ?? false),
        ], $replyMarkup);

        if (isset($poll['correct_option_id'])) {
            $payload['correct_option_id'] = $poll['correct_option_id'];
        }
        if (isset($poll['explanation']) && $poll['explanation'] !== '') {
            $payload['explanation'] = $poll['explanation'];
            $payload['explanation_parse_mode'] = 'HTML';
        }
        if (isset($poll['open_period'])) {
            $payload['open_period'] = $poll['open_period'];
        }

        return $this->post('sendPoll', $payload + $flags);
    }

    /**
     * Acknowledge a callback_query — toast text or a modal alert.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $alert = false): ApiResult
    {
        return $this->post('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $alert,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * deleteMessage — used by broadcast recall.
     */
    public function deleteMessage(int|string $chatId, int $messageId): ApiResult
    {
        return $this->post('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * POST a JSON payload and normalize the result.
     *
     * @param  array<string, mixed>  $payload
     */
    private function post(string $method, array $payload): ApiResult
    {
        $token = config('services.telegram.bot_token');

        $response = Http::timeout(10)->post(self::BASE."/bot{$token}/{$method}", $payload);

        return ApiResult::fromResponse($response);
    }

    /**
     * POST multipart — one named file field, or a map of attach:// parts
     * for sendMediaGroup.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $uploads
     */
    private function postUpload(string $method, array $payload, ?string $field, ?string $path, array $uploads = []): ApiResult
    {
        $token = config('services.telegram.bot_token');

        $request = Http::timeout(60);

        $paths = $field !== null && $path !== null
            ? [$field => $path] + $uploads
            : $uploads;

        foreach ($paths as $name => $filePath) {
            $handle = fopen($filePath, 'r');

            if ($handle === false) {
                return new ApiResult(ok: false, description: "Cannot read file: {$filePath}");
            }

            $request = $request->attach($name, $handle, basename($filePath));
        }

        // Multipart requests serialize every field — nested structures go
        // as JSON strings per the Bot API docs.
        $flattened = [];
        foreach ($payload as $key => $value) {
            $flattened[$key] = is_array($value) || is_bool($value)
                ? json_encode($value)
                : $value;
        }

        $response = $request->post(self::BASE."/bot{$token}/{$method}", $flattened);

        return ApiResult::fromResponse($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $replyMarkup
     * @return array<string, mixed>
     */
    private function withMarkup(array $payload, ?array $replyMarkup): array
    {
        if ($replyMarkup !== null && $replyMarkup !== []) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $payload;
    }

    /**
     * Whether the media reference is a local file to upload rather than a
     * URL or cached file_id.
     */
    private function isUpload(string $media): bool
    {
        return ! str_starts_with($media, 'http://')
            && ! str_starts_with($media, 'https://')
            && is_file($media);
    }
}
