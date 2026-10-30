<?php

namespace App\Support\Telegram;

use App\Models\User;

/**
 * Resolves a Telegram profile — a bot update's `from` object or a verified
 * Mini App `user` payload — to a local account, creating one on the fly
 * when unknown.
 */
class UserResolver
{
    /**
     * Find the account for a Telegram user, creating one when unknown.
     *
     * @param  array<string, mixed>  $from
     */
    public function resolve(array $from, ?string $chatId = null): User
    {
        $telegramId = $this->telegramId($from);

        $user = $telegramId === null
            ? null
            : User::query()->where('telegram_id', $telegramId)->first();

        // A handle may be stale on another account (users change Telegram
        // usernames) — never write a value that would hit the unique index.
        $telegramUsername = $this->telegramUsername($from);

        if ($telegramUsername !== null && User::query()
            ->where('telegram_username', $telegramUsername)
            ->when($user !== null, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists()) {
            $telegramUsername = null;
        }

        if ($user !== null) {
            $attributes = ['telegram_username' => $telegramUsername];

            if ($chatId !== null) {
                $attributes['telegram_chat_id'] = $chatId;
            }

            $user->forceFill($attributes)->save();

            return $user;
        }

        return User::create([
            'name' => $this->displayName($from),
            'username' => $this->availableUsername($from),
            'email' => null,
            'password' => null,
            'telegram_id' => $telegramId,
            'telegram_username' => $telegramUsername,
            'telegram_chat_id' => $chatId,
        ]);
    }

    /**
     * Telegram's numeric user id from the `from` object.
     *
     * @param  array<string, mixed>  $from
     */
    public function telegramId(array $from): ?int
    {
        $id = $from['id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Telegram's @username, lowercased, from the `from` object.
     *
     * @param  array<string, mixed>  $from
     */
    public function telegramUsername(array $from): ?string
    {
        $username = $from['username'] ?? null;

        return is_string($username) && $username !== '' ? strtolower($username) : null;
    }

    /**
     * Display name for auto-created accounts.
     *
     * @param  array<string, mixed>  $from
     */
    private function displayName(array $from): string
    {
        $name = trim(implode(' ', array_filter([
            $from['first_name'] ?? null,
            $from['last_name'] ?? null,
        ], fn ($part) => is_string($part) && $part !== '')));

        return $name !== '' ? mb_substr($name, 0, 255) : 'Telegram User';
    }

    /**
     * The Telegram @username when it is free across both username columns.
     *
     * @param  array<string, mixed>  $from
     */
    private function availableUsername(array $from): ?string
    {
        $username = $this->telegramUsername($from);

        if ($username === null || ! preg_match('/^[a-z0-9_-]{3,30}$/', $username)) {
            return null;
        }

        $taken = User::query()
            ->where('username', $username)
            ->orWhere('telegram_username', $username)
            ->exists();

        return $taken ? null : $username;
    }
}
