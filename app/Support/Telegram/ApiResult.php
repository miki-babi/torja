<?php

namespace App\Support\Telegram;

use Illuminate\Http\Client\Response;

/**
 * Normalized Bot API call outcome: message ids and file ids pulled from
 * successful payloads (albums yield several of each); failures expose
 * `error_code`, `description` and `retry_after` so callers can
 * distinguish blocked users, rate limits, and real errors.
 */
class ApiResult
{
    /**
     * @param  array<int, int>  $messageIds
     * @param  array<int, string>  $fileIds
     * @param  array<string, mixed>|array<int, mixed>  $result
     */
    public function __construct(
        public readonly bool $ok,
        public readonly array $messageIds = [],
        public readonly array $fileIds = [],
        public readonly ?int $errorCode = null,
        public readonly ?string $description = null,
        public readonly ?int $retryAfter = null,
        public readonly array $result = [],
    ) {}

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $ok = (bool) ($body['ok'] ?? false);

        if (! $ok) {
            return new self(
                ok: false,
                errorCode: $body['error_code'] ?? $response->status(),
                description: $body['description'] ?? $response->body(),
                retryAfter: $body['parameters']['retry_after'] ?? null,
            );
        }

        $result = $body['result'] ?? [];

        // sendMediaGroup returns an array of Message; the others one Message.
        $messages = isset($result['message_id']) ? [$result] : (is_array($result) && array_is_list($result) ? $result : []);

        $messageIds = [];
        $fileIds = [];

        foreach ($messages as $message) {
            if (isset($message['message_id'])) {
                $messageIds[] = $message['message_id'];
            }
            foreach (['photo', 'video', 'document', 'animation', 'audio'] as $kind) {
                $attachment = $message[$kind] ?? null;
                if (is_array($attachment)) {
                    // Photos arrive as an array of sizes — biggest is last.
                    $file = isset($attachment['file_id']) ? $attachment : end($attachment);
                    if (is_array($file) && isset($file['file_id'])) {
                        $fileIds[] = $file['file_id'];
                    }
                }
            }
        }

        return new self(
            ok: true,
            messageIds: $messageIds,
            fileIds: $fileIds,
            result: is_array($result) ? $result : [],
        );
    }

    /**
     * Whether the failure is the user blocking the bot / chat gone.
     */
    public function isBlocked(): bool
    {
        return $this->errorCode === 403;
    }

    /**
     * Whether the call hit Telegram's rate limit.
     */
    public function isRateLimited(): bool
    {
        return $this->errorCode === 429;
    }
}
