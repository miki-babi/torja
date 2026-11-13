<?php

namespace App\Support\Telegram;

use App\Models\Exercise;
use App\Models\WorkoutSet;

/**
 * Handles `inline_query` updates — the inline mode flow that lets
 * members share exercises into any chat. A query is
 * answered with InlineQueryResultGif cards: the animated demo, a short
 * HTML caption, and a `t.me/<bot>[/<app>]?startapp=ex_<slug>` url button
 * (web_app buttons are rejected on inline-mode messages, so the deep
 * link is how the shared exercise opens in the Mini App).
 *
 * An empty query still returns results — exercises ordered by how often
 * members log sets for them — so the picker shows content immediately,
 * the way inline bots like @gif and @bing do.
 */
class InlineQueries
{
    private const PAGE_SIZE = 10;

    /**
     * Answer an `inline_query` update with matching exercise results.
     * Telegram passes `offset` back verbatim for pagination.
     *
     * @param  array<string, mixed>  $inlineQuery
     */
    public function handle(array $inlineQuery): void
    {
        $queryId = (string) ($inlineQuery['id'] ?? '');

        if ($queryId === '') {
            return;
        }

        $term = trim((string) ($inlineQuery['query'] ?? ''));
        $offset = max(0, (int) ($inlineQuery['offset'] ?? 0));
        $language = is_array($inlineQuery['from'] ?? null)
            ? (string) ($inlineQuery['from']['language_code'] ?? '')
            : '';

        [$results, $nextOffset] = $this->search($term, $offset, $language);

        $options = [
            'cache_time' => 300,
            'is_personal' => false,
        ];

        if ($nextOffset !== null) {
            $options['next_offset'] = (string) $nextOffset;
        }

        try {
            app(TelegramApi::class)->answerInlineQuery($queryId, $results, $options);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Exercises mapped to inline results, plus the next offset when
     * another page exists. One extra row is fetched to detect the next
     * page without a COUNT query. Non-empty terms name-match; an empty
     * term lists the catalog by logged-set popularity — a joined GROUP
     * BY rather than a correlated count, since `exercise_id` isn't the
     * leftmost column of the workout_sets unique index.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int|null}
     */
    private function search(string $term, int $offset, string $language): array
    {
        $escaped = addcslashes($term, '%_\\');

        $popularity = WorkoutSet::query()
            ->select('exercise_id')
            ->selectRaw('count(*) as set_count')
            ->groupBy('exercise_id');

        $exercises = Exercise::query()
            ->select('exercises.*')
            ->when($term !== '', fn ($query) => $query
                ->where(fn ($w) => $w
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('name_am', 'like', "%{$escaped}%"))
                ->orderBy('exercises.name'))
            ->when($term === '', fn ($query) => $query
                ->leftJoinSub($popularity, 'set_counts', 'set_counts.exercise_id', '=', 'exercises.slug')
                ->orderByRaw('COALESCE(set_counts.set_count, 0) DESC')
                ->orderBy('exercises.name'))
            ->offset($offset)
            ->limit(self::PAGE_SIZE + 1)
            ->get();

        $nextOffset = $exercises->count() > self::PAGE_SIZE
            ? $offset + self::PAGE_SIZE
            : null;

        return [
            $exercises->take(self::PAGE_SIZE)
                ->map(fn (Exercise $exercise): array => $this->result($exercise, $language))
                ->values()
                ->all(),
            $nextOffset,
        ];
    }

    /**
     * One InlineQueryResultGif: the demo gif, a caption with name,
     * equipment/target and level, and the Mini App deep-link button.
     *
     * @return array<string, mixed>
     */
    private function result(Exercise $exercise, string $language): array
    {
        $name = $language === 'am' && $exercise->name_am !== null
            ? $exercise->name_am
            : $exercise->name;

        $result = [
            'type' => 'gif',
            'id' => $exercise->slug,
            'gif_url' => $exercise->gif_url,
            'thumbnail_url' => $exercise->image_url,
            'title' => $name,
            'caption' => $this->caption($exercise, $name),
            'parse_mode' => 'HTML',
        ];

        $deepLink = $this->deepLink('ex_'.$exercise->slug);

        if ($deepLink !== null) {
            $result['reply_markup'] = [
                'inline_keyboard' => [[
                    [
                        'text' => '💪 Open in '.$this->appName(),
                        'url' => $deepLink,
                    ],
                ]],
            ];
        }

        return $result;
    }

    /**
     * Telegram-HTML caption: bold name over the "Equipment · Target"
     * detail line and the level.
     */
    private function caption(Exercise $exercise, string $name): string
    {
        return implode("\n", [
            '<b>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</b>',
            htmlspecialchars($exercise->detail(), ENT_QUOTES, 'UTF-8'),
            'Level: '.htmlspecialchars(ucfirst($exercise->level), ENT_QUOTES, 'UTF-8'),
        ]);
    }

    /**
     * A `t.me/<bot>[/<app>]?startapp=<param>` direct link — the same
     * shape {@see User::inviteUrl()} builds for referrals.
     */
    private function deepLink(string $startParam): ?string
    {
        $bot = config('services.telegram.bot_username');

        if (! is_string($bot) || $bot === '') {
            return null;
        }

        $app = config('services.telegram.mini_app_name');
        $app = is_string($app) && $app !== '' ? "/{$app}" : '';

        return "https://t.me/{$bot}{$app}?startapp={$startParam}";
    }

    private function appName(): string
    {
        return (string) config('app.name');
    }
}
