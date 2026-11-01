<?php

namespace App\Support\Telegram;

/**
 * The persistent reply-keyboard menu shown at the bottom of the bot chat.
 * Tapping a button sends its label as a normal message; the webhook then
 * answers with a message carrying an inline `web_app` button that deep
 * links into the mini app (a message can carry either an inline keyboard
 * or a reply keyboard, never both — the reply keyboard is persistent, so
 * it stays visible after the tap).
 */
class MenuKeyboard
{
    /**
     * Ordered keyboard rows. Each entry is the button label users tap, the
     * app path the inline button opens, the reply copy, and the inline
     * button label. `{app}` in `message` is replaced with the app name.
     *
     * @var array<int, array<int, array{label: string, path: string, message: string, button: string}>>
     */
    private const ROWS = [
        [
            [
                'label' => '🚀 Open App',
                'path' => '/',
                'message' => 'Tap below to open {app}.',
                'button' => 'Open {app}',
            ],
        ],
        [
            [
                'label' => '🏋️ Today\'s workout',
                'path' => '/today',
                'message' => 'Tap below to start today\'s workout.',
                'button' => 'Start workout',
            ],
            [
                'label' => '👥 Invite friends',
                'path' => '/ambassador',
                'message' => 'Tap below to grab your invite link.',
                'button' => 'Get invite link',
            ],
        ],
    ];

    /**
     * The ReplyKeyboardMarkup attached to ordinary bot replies so the menu
     * stays visible (and self-heals when removed).
     *
     * @return array{keyboard: array<int, array<int, array{text: string}>>, resize_keyboard: true, is_persistent: true}
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
     * web_app button — or null when the text matches no button.
     *
     * @return array{text: string, reply_markup: array{inline_keyboard: array<int, array<int, array{text: string, web_app: array{url: string}}>>}}|null
     */
    public function responseFor(string $text): ?array
    {
        foreach (self::ROWS as $row) {
            foreach ($row as $button) {
                if ($button['label'] !== $text) {
                    continue;
                }

                return [
                    'text' => $this->render($button['message']),
                    'reply_markup' => [
                        'inline_keyboard' => [[
                            [
                                'text' => $this->render($button['button']),
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
     * Render `{app}` placeholders with the app name.
     */
    private function render(string $template): string
    {
        return str_replace('{app}', (string) config('app.name'), $template);
    }
}
