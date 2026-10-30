<?php

namespace App\Support\Telegram;

/**
 * Parses and verifies the `initData` payload Telegram injects when the app
 * is opened inside a Mini App. Signature scheme per Telegram docs: sort the
 * fields, join `key=value` pairs with newlines, then HMAC them with a key
 * derived from the bot token and the literal "WebAppData".
 *
 * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class InitData
{
    /**
     * Seconds a payload stays acceptable after `auth_date`.
     */
    private const MAX_AGE = 86400;

    /**
     * @param  array<string, string>  $data
     */
    private function __construct(
        private readonly array $data,
    ) {}

    /**
     * Parse and verify a raw initData query string. Returns null when the
     * signature is invalid, the payload is stale, or fields are malformed;
     * `$failure` then reports which gate rejected it.
     */
    public static function verify(string $initData, string $botToken, ?string &$failure = null): ?self
    {
        parse_str($initData, $data);

        $hash = $data['hash'] ?? null;

        if (! is_string($hash) || $hash === '') {
            $failure = 'no-hash';

            return null;
        }

        unset($data['hash']);

        $pairs = [];

        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                $failure = 'malformed';

                return null;
            }

            $pairs[$key] = "{$key}={$value}";
        }

        ksort($pairs);

        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expected = hash_hmac('sha256', implode("\n", $pairs), $secret);

        if (! hash_equals($expected, $hash)) {
            $failure = 'bad-signature';

            return null;
        }

        $authDate = (int) ($data['auth_date'] ?? 0);

        if ($authDate <= 0 || abs(time() - $authDate) > self::MAX_AGE) {
            $failure = 'stale-auth-date';

            return null;
        }

        return new self($data);
    }

    /**
     * The Telegram user object embedded in the payload, or null when absent
     * or malformed.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $user = json_decode($this->data['user'] ?? '', true);

        return is_array($user) && isset($user['id']) ? $user : null;
    }
}
