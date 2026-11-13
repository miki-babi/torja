<?php

namespace App\Support\Telegram;

/**
 * The persistent reply-keyboard menu shown at the bottom of the bot chat.
 * Tapping a button sends its label as a normal message; the webhook then
 * answers with a message carrying an inline `web_app` button that deep
 * links into the mini app (a message can carry either an inline keyboard
 * or a reply keyboard, never both — the reply keyboard is persistent, so
 * it stays visible after the tap).
 *
 * The main bot is the coach funnel: every row serves trainer onboarding.
 * Buttons with a `path` answer with a web_app deep link; a `path` of null
 * answers with plain text (the invite link) and re-shows the keyboard.
 */
class MenuKeyboard
{
    /**
     * Ordered keyboard rows. Each entry is the button label users tap, the
     * app path the inline button opens (null for a text-only reply), the
     * reply copy, and the inline button label. `{app}` renders the app name
     * and `{invite}` the coach invite deep link.
     *
     * @var array<int, array<int, array{label: string, path: ?string, message: string, button: ?string}>>
     */
    private const ROWS = [
        [
            [
                'label' => '🚀 Open App',
                'path' => '/',
                'message' => 'Tap below to open {app} — set up your coaching bot to go live.',
                'button' => 'Open {app}',
            ],
        ],
        [
            [
                'label' => '🤖 Bot setup',
                'path' => '/trainer/bot',
                'message' => 'Connect your bot token — this is the bot your clients talk to.',
                'button' => 'Bot setup',
            ],
            [
                'label' => '👥 Invite trainers',
                'path' => null,
                'message' => "Know a coach? Share this link — it opens {app} as a trainer:\n\n{invite}",
                'button' => null,
            ],
        ],
    ];

    /**
     * The ReplyKeyboardMarkup attached to ordinary bot replies so the menu
     * stays visible (and self-heals when removed).
     *
     * @return array{keyboard: array<int, array<int, array<string, mixed>>>, resize_keyboard: true, is_persistent: true}
     */
    public function markup(): array
    {
        $keyboard = [];

        foreach (self::ROWS as $row) {
            $keyboard[] = array_map(
                fn (array $button): array => ['text' => $button['label']],
                $row,
            );
        }

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    /**
     * The response for a tapped menu button — a message with an inline
     * web_app button, or a plain text reply for path-less buttons — or
     * null when the text matches no button.
     *
     * @return array{text: string, reply_markup: array<string, mixed>}|null
     */
    public function responseFor(string $text): ?array
    {
        foreach (self::ROWS as $row) {
            foreach ($row as $button) {
                if ($button['label'] !== $text) {
                    continue;
                }

                if ($button['path'] === null) {
                    return [
                        'text' => $this->render($button['message']),
                        'reply_markup' => $this->markup(),
                    ];
                }

                return [
                    'text' => $this->render($button['message']),
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            [
                                'text' => $this->render($button['button'] ?? ''),
                                'web_app' => [
                                    'url' => $this->baseUrl().$button['path'],
                                ],
                            ],
                        ]],
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * The public https URL the mini app is served from — `web_app` buttons
     * reject anything else. Falls back to APP_URL when no dedicated URL
     * is configured.
     */
    private function baseUrl(): string
    {
        $url = config('services.telegram.mini_app_url');

        if (is_string($url) && $url !== '') {
            return rtrim($url, '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Render `{app}` with the app name and `{invite}` with the deep link
     * that opens the mini app for a new coach (`?startapp=<mini app name>`).
     */
    private function render(string $template): string
    {
        $bot = (string) config('services.telegram.bot_username');
        $miniApp = (string) config('services.telegram.mini_app_name');

        $invite = "https://t.me/{$bot}".($miniApp !== '' ? "?startapp={$miniApp}" : '');

        return str_replace(
            ['{app}', '{invite}'],
            [(string) config('app.name'), $invite],
            $template,
        );
    }
}
