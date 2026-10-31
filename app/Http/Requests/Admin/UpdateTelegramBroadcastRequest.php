<?php

namespace App\Http\Requests\Admin;

use App\Enums\TelegramBroadcastType;
use App\Enums\TelegramButtonKind;
use App\Support\Segments;
use App\Support\Telegram\TelegramHtml;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Broadcast composer payload. Validates the raw form shape, then converts
 * `body_html` and per-item captions through {@see TelegramHtml} — the
 * converted `telegram_text` fields are what jobs send, and their lengths
 * are checked against Telegram's caption/body budgets here.
 */
class UpdateTelegramBroadcastRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(TelegramBroadcastType::values())],
            'body_html' => ['nullable', 'string', 'max:50000'],

            'media' => ['nullable', 'array'],
            'media.*.kind' => ['required', 'string', Rule::in(['photo', 'video', 'document', 'animation'])],
            'media.*.value' => ['required', 'string', 'max:2000'],
            'media.*.source' => ['required', 'string', Rule::in(['path', 'url', 'file_id'])],
            'media.*.caption_html' => ['nullable', 'string', 'max:20000'],
            'media.*.file_id' => ['nullable', 'string', 'max:500'],

            'buttons' => ['nullable', 'array', 'max:10'],
            'buttons.*' => ['array', 'max:8'],
            'buttons.*.*.text' => ['required', 'string', 'max:64'],
            'buttons.*.*.kind' => ['required', 'string', Rule::in(TelegramButtonKind::values())],
            'buttons.*.*.style' => ['nullable', 'string', Rule::in(['primary', 'danger', 'success'])],
            'buttons.*.*.url' => [
                'nullable', 'string', 'max:2000',
                Rule::requiredIf(fn (): bool => $this->buttonKindsNeed('url', 'web_app', 'login_url')),
            ],
            'buttons.*.*.query' => ['nullable', 'string', 'max:256'],
            'buttons.*.*.copy_text' => ['nullable', 'string', 'max:256'],
            'buttons.*.*.action' => ['nullable', 'string', Rule::in(['toast', 'track'])],
            'buttons.*.*.toast' => ['nullable', 'string', 'max:200'],
            'buttons.*.*.show_alert' => ['nullable', 'boolean'],
            'buttons.*.*.forward_text' => ['nullable', 'string', 'max:64'],
            'buttons.*.*.request_write_access' => ['nullable', 'boolean'],
            'buttons.*.*.allow_user_chats' => ['nullable', 'boolean'],
            'buttons.*.*.allow_bot_chats' => ['nullable', 'boolean'],
            'buttons.*.*.allow_group_chats' => ['nullable', 'boolean'],
            'buttons.*.*.allow_channel_chats' => ['nullable', 'boolean'],

            'poll' => ['nullable', 'array'],
            'poll.question' => ['nullable', 'string', 'max:300'],
            'poll.options' => ['nullable', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['required', 'string', 'min:1', 'max:100'],
            'poll.type' => ['nullable', 'string', Rule::in(['regular', 'quiz'])],
            'poll.is_anonymous' => ['nullable', 'boolean'],
            'poll.allows_multiple_answers' => ['nullable', 'boolean'],
            'poll.correct_option_id' => ['nullable', 'integer', 'min:0'],
            'poll.explanation' => ['nullable', 'string', 'max:200'],
            'poll.open_period' => ['nullable', 'integer', 'min:5', 'max:600'],

            'options.disable_notification' => ['nullable', 'boolean'],
            'options.protect_content' => ['nullable', 'boolean'],

            'audience' => ['required', 'array'],
            'audience.everyone' => ['nullable', 'boolean'],
            'audience.segments' => ['nullable', 'array', 'max:20'],
            'audience.segments.*' => ['string', Rule::in(array_keys(Segments::labels()))],
            'audience.user_ids' => ['nullable', 'array', 'max:500'],
            'audience.user_ids.*' => ['integer', 'exists:users,id'],

            'scheduled_at' => ['nullable', 'date'],
            'recurrence' => ['nullable', 'string', Rule::in(['none', 'daily', 'weekly', 'monthly'])],
            'send_mode' => ['nullable', 'string', Rule::in(['draft', 'now', 'schedule'])],
        ];
    }

    /**
     * Drop payload sections the selected type can't use before rules run
     * — the composer keeps a poll/media draft in state when the type
     * changes away, and posts it along. Without this, a text broadcast
     * carrying an empty poll fails `poll.options.*` rules. Also strips
     * empty button rows (mirroring {@see payload()}) so an untouched row
     * doesn't trip the `text` requirement.
     */
    protected function prepareForValidation(): void
    {
        $type = TelegramBroadcastType::tryFrom((string) $this->input('type'));

        $merge = [
            'buttons' => collect((array) $this->input('buttons', []))
                ->map(fn (mixed $row): array => array_values(array_filter(
                    (array) $row,
                    fn (mixed $button): bool => trim((string) ($button['text'] ?? '')) !== '',
                )))
                ->filter(fn (array $row): bool => $row !== [])
                ->values()
                ->all(),
        ];

        if ($type !== null && $type !== TelegramBroadcastType::Poll) {
            $merge['poll'] = null;
        }

        if ($type !== null && ! $type->isSingleMedia() && $type !== TelegramBroadcastType::MediaGroup) {
            $merge['media'] = null;
        }

        $this->merge($merge);
    }

    /**
     * Post-validation: per-type content requirements, converted-text
     * length budgets, album sizes, and an effective (non-empty) audience.
     */
    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->checkContent($validator);
                $this->checkAudience($validator);
                $this->checkSchedule($validator);
            },
        ];
    }

    /**
     * Whether any submitted button has one of the given kinds — used by
     * the conditional `url` requirement.
     */
    private function buttonKindsNeed(string ...$kinds): bool
    {
        foreach ((array) $this->input('buttons', []) as $row) {
            foreach ((array) $row as $button) {
                if (in_array($button['kind'] ?? null, $kinds, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Type-specific payload checks and converted-text limits.
     */
    private function checkContent(Validator $validator): void
    {
        $type = TelegramBroadcastType::tryFrom((string) $this->input('type')) ?? TelegramBroadcastType::Text;
        $media = (array) $this->input('media', []);
        $poll = (array) $this->input('poll', []);
        $pollOptions = (array) ($poll['options'] ?? []);

        $text = TelegramHtml::toTelegram((string) $this->input('body_html', ''));
        $limit = $type->usesCaption() ? 1024 : 4096;

        if ($type === TelegramBroadcastType::Text && $text === '') {
            $validator->errors()->add('body_html', 'The message text is required.');
        }

        if (mb_strlen($text) > $limit) {
            $validator->errors()->add('body_html', 'The message converts to '.mb_strlen($text)." characters — Telegram allows {$limit} here.");
        }

        if ($type->isSingleMedia() && count($media) !== 1) {
            $validator->errors()->add('media', 'Attach exactly one file or URL.');
        }

        if ($type === TelegramBroadcastType::MediaGroup) {
            if (count($media) < 2 || count($media) > 10) {
                $validator->errors()->add('media', 'An album needs between 2 and 10 items.');
            }

            foreach ($media as $i => $item) {
                $item = (array) $item;

                if (! in_array($item['kind'] ?? '', TelegramBroadcastType::mediaItemKinds(), true)) {
                    $validator->errors()->add("media.{$i}.kind", 'Album items must be photos or videos.');
                }

                $caption = TelegramHtml::toTelegram((string) ($item['caption_html'] ?? ''));

                if (mb_strlen($caption) > 1024) {
                    $validator->errors()->add("media.{$i}.caption_html", 'Captions are limited to 1024 characters.');
                }
            }
        }

        if ($type === TelegramBroadcastType::Poll) {
            if (trim((string) ($poll['question'] ?? '')) === '') {
                $validator->errors()->add('poll.question', 'The poll question is required.');
            }

            if (count($pollOptions) < 2) {
                $validator->errors()->add('poll.options', 'Add at least two options.');
            }

            if (($poll['type'] ?? 'regular') === 'quiz' && ! isset($poll['correct_option_id'])) {
                $validator->errors()->add('poll.correct_option_id', 'Pick the correct answer for the quiz.');
            } elseif (isset($poll['correct_option_id']) && $poll['correct_option_id'] >= count($pollOptions)) {
                $validator->errors()->add('poll.correct_option_id', 'The correct answer must be one of the options.');
            }
        }

        foreach ((array) $this->input('buttons', []) as $r => $row) {
            foreach ((array) $row as $c => $button) {
                if (($button['kind'] ?? '') === 'web_app' && ! str_starts_with((string) ($button['url'] ?? ''), 'https://')) {
                    $validator->errors()->add("buttons.{$r}.{$c}.url", 'Mini App buttons need an https:// URL.');
                }
            }
        }
    }

    /**
     * The audience must select at least one effective rule.
     */
    private function checkAudience(Validator $validator): void
    {
        $audience = (array) $this->input('audience', []);

        if (($audience['everyone'] ?? false) === true) {
            return;
        }

        if (count((array) ($audience['segments'] ?? [])) === 0 && count((array) ($audience['user_ids'] ?? [])) === 0) {
            $validator->errors()->add('audience', 'Pick everyone, at least one segment, or specific users.');
        }
    }

    /**
     * Scheduling needs a future-ish date; recurrence needs the schedule too.
     */
    private function checkSchedule(Validator $validator): void
    {
        $scheduledAt = $this->input('scheduled_at');
        $recurrence = (string) $this->input('recurrence', 'none');

        if ($this->input('send_mode') === 'schedule' && $scheduledAt === null) {
            $validator->errors()->add('scheduled_at', 'Pick when the broadcast should go out.');
        }

        if ($recurrence !== 'none' && $scheduledAt === null) {
            $validator->errors()->add('scheduled_at', 'Recurring broadcasts need a first send time.');
        }

        if ($scheduledAt !== null && strtotime((string) $scheduledAt) < time() - 300) {
            $validator->errors()->add('scheduled_at', 'The send time is in the past.');
        }
    }

    /**
     * The normalized, DB-ready broadcast payload — converted text baked in.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        $data['telegram_text'] = TelegramHtml::toTelegram((string) ($data['body_html'] ?? ''));

        $data['media'] = array_map(function (array $item): array {
            $item['caption_telegram'] = TelegramHtml::toTelegram((string) ($item['caption_html'] ?? ''));

            return $item;
        }, $data['media'] ?? []);

        // Drop empty button rows the UI may submit.
        $data['buttons'] = array_values(array_filter(
            array_map(
                fn (array $row): array => array_values(array_filter(
                    $row,
                    fn (array $b): bool => trim((string) ($b['text'] ?? '')) !== '',
                )),
                $data['buttons'] ?? [],
            ),
            fn (array $row): bool => $row !== [],
        )) ?: null;

        $data['audience'] = [
            'everyone' => (bool) ($data['audience']['everyone'] ?? false),
            'segments' => array_values($data['audience']['segments'] ?? []),
            'user_ids' => array_values(array_map('intval', $data['audience']['user_ids'] ?? [])),
        ];

        $data['options'] = [
            'disable_notification' => (bool) ($data['options']['disable_notification'] ?? false),
            'protect_content' => (bool) ($data['options']['protect_content'] ?? false),
        ];

        $data['recurrence'] = $data['recurrence'] ?? 'none';

        // Drop payload fields the selected type can't use.
        $type = TelegramBroadcastType::from($data['type']);

        if (! $type->isSingleMedia() && $type !== TelegramBroadcastType::MediaGroup) {
            $data['media'] = [];
        }
        if ($type !== TelegramBroadcastType::Poll) {
            $data['poll'] = null;
        } elseif (isset($data['poll']['explanation'])) {
            // The explanation input is plain text — escape it for
            // explanation_parse_mode=HTML.
            $data['poll']['explanation'] = htmlspecialchars(
                strip_tags((string) $data['poll']['explanation']),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            );
        }

        unset($data['send_mode']);

        return $data;
    }

    /**
     * Surface silent 422s — the composer may not render every error key,
     * so rejections are recorded in the log for diagnosis.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('broadcast.validation_failed', [
            'route' => $this->route()?->getName(),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
