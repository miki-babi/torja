<?php

namespace App\Support\Nudges;

use App\Support\Translator;

/**
 * Catalog of lifecycle nudges, in priority order (first match wins when
 * several apply). Slugs double as `Segments` keys for proactive sends and
 * as the `nudge` property on `nudge.sent`/`nudge.dismissed` tracked events.
 */
class NudgeRegistry
{
    /**
     * Slug → config. `cooldown_hours` suppresses repeat sends/shows within
     * the window; `route` is the action destination.
     *
     * @var array<string, array{cooldown_hours: int, route: string}>
     */
    private const DEFINITIONS = [
        'checkout_abandoned' => ['cooldown_hours' => 24, 'route' => 'pro.show'],
        'plan_ready_unpaid' => ['cooldown_hours' => 96, 'route' => 'plan.preview'],
        'wizard_abandoned' => ['cooldown_hours' => 72, 'route' => 'plan.wizard'],
    ];

    /**
     * All nudge slugs, highest priority first.
     *
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * Re-show / re-send suppression window for a nudge.
     */
    public static function cooldownHours(string $slug): int
    {
        return self::DEFINITIONS[$slug]['cooldown_hours'] ?? 72;
    }

    /**
     * The URL the nudge's call-to-action points at.
     */
    public static function actionUrl(string $slug): string
    {
        return route(self::routeName($slug));
    }

    /**
     * The route name the nudge's call-to-action points at.
     */
    public static function routeName(string $slug): string
    {
        return self::DEFINITIONS[$slug]['route'] ?? 'plan.wizard';
    }

    /**
     * Translated, personalized payload for one nudge.
     *
     * @return array{slug: string, title: string, body: string, cta: string, url: string}
     */
    public static function payload(string $slug, ?string $goal = null, ?int $stepsLeft = null, ?string $locale = null): array
    {
        $goalLabel = $goal === null
            ? Translator::t('nudges', 'goal_default', $locale)
            : (Translator::t('nudges', 'goal_'.$goal, $locale));

        $replace = [
            ':goal' => $goalLabel,
            ':steps' => (string) ($stepsLeft ?? ''),
        ];

        return [
            'slug' => $slug,
            'title' => strtr(Translator::t('nudges', $slug.'.title', $locale), $replace),
            'body' => strtr(Translator::t('nudges', $slug.'.body', $locale), $replace),
            'cta' => Translator::t('nudges', $slug.'.cta', $locale),
            'url' => self::actionUrl($slug),
        ];
    }
}
