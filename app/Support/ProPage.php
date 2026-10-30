<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Validation\Rule;

/**
 * Admin-editable copy, feature table and price for the /pro upgrade screen.
 * Defaults ship in code; overrides live in the `pro_page` setting. Every
 * text field is a {en, am} map, resolved to the current locale (English
 * fallback) by forMember(). `{price}` inside any text is replaced with the
 * resolved price.
 */
class ProPage
{
    public const SETTING_KEY = 'pro_page';

    /**
     * Icon slugs the admin can pick for feature rows and trust badges. The
     * frontend maps each slug to a lucide icon.
     *
     * @var array<int, string>
     */
    public const ICONS = [
        'dumbbell',
        'salad',
        'calendar-days',
        'refresh-cw',
        'infinity',
        'sparkles',
        'badge-check',
        'smartphone',
        'shield-check',
        'zap',
        'crown',
        'heart',
        'flame',
        'trophy',
    ];

    /**
     * Text fields at the top level of the settings payload with their max
     * lengths. Used for defaults, validation and localization alike.
     *
     * @var array<string, int>
     */
    private const TEXT_FIELDS = [
        'badge' => 40,
        'headline' => 60,
        'headline_accent' => 60,
        'subheadline' => 160,
        'starter_label' => 24,
        'pro_label' => 24,
        'unlock_button' => 40,
        'sheet_title' => 60,
        'sheet_subtitle' => 100,
        'price_label' => 40,
        'price_pill' => 24,
        'sheet_button' => 40,
        'pro_title' => 60,
        'pro_body' => 160,
        'free_button' => 40,
        'free_notice' => 160,
        'card_title' => 60,
        'card_body' => 120,
        'card_button' => 40,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'price_etb' => null,
            'badge' => self::text('Special offer'),
            'headline' => self::text('Go Pro.'),
            'headline_accent' => self::text('Train Better.'),
            'subheadline' => self::text('Everything you need to build your strongest self.'),
            'starter_label' => self::text('Starter'),
            'pro_label' => self::text('Pro'),
            'features' => [
                self::feature('dumbbell', 'Workout programs', 'Structured plans for your goals.'),
                self::feature('salad', 'Meal programs', 'Nutrition plans for better results.'),
                self::feature('calendar-days', 'Full 7-day meal plan', 'Pre-planned meals for maximum consistency.'),
                self::feature('refresh-cw', 'Meal swaps', 'Flexibility for your lifestyle.'),
                self::feature('infinity', 'Lifetime access', 'Keep your progress, forever.'),
            ],
            'unlock_button' => self::text('Unlock lifetime access'),
            'sheet_title' => self::text('TrainPro Pro'),
            'sheet_subtitle' => self::text('One payment. Yours forever.'),
            'price_label' => self::text('Lifetime access'),
            'price_pill' => self::text('Lifetime'),
            'trust_badges' => [
                self::badge('shield-check', 'Secure payment'),
                self::badge('zap', 'Instant access'),
                self::badge('infinity', 'No subscriptions. No renewals.'),
            ],
            'sheet_button' => self::text('Continue to payment'),
            'pro_title' => self::text("You're Pro"),
            'pro_body' => self::text('Every program and meal plan is yours to claim.'),
            'free_button' => self::text('Continue with free'),
            'free_notice' => self::text('Keep using TrainPro for free — you can unlock Pro anytime from your profile.'),
            'card_title' => self::text('TrainPro Pro'),
            'card_body' => self::text('Unlock every workout and meal program forever.'),
            'card_button' => self::text('Go Pro'),
        ];
    }

    /**
     * Defaults overlaid with whatever the admin saved. Lists (features,
     * trust badges) are taken wholesale so removed rows stay removed.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $stored = Setting::get(self::SETTING_KEY, []);
        $stored = is_array($stored) ? $stored : [];

        $merged = array_replace_recursive(self::defaults(), $stored);

        foreach (['features', 'trust_badges'] as $list) {
            if (array_key_exists($list, $stored) && is_array($stored[$list])) {
                $merged[$list] = array_values($stored[$list]);
            }
        }

        return $merged;
    }

    /**
     * The one-time Pro price in ETB: admin override first, env fallback.
     */
    public static function price(): int
    {
        $stored = self::settings()['price_etb'] ?? null;

        return (int) ($stored ?: config('plans.pro_price_etb'));
    }

    /**
     * The page copy resolved for the current locale, ready for the member
     * screen.
     *
     * @return array<string, mixed>
     */
    public static function forMember(?string $locale = null): array
    {
        return self::localize(self::settings(), $locale ?? app()->getLocale(), self::price());
    }

    /**
     * Resolve a raw settings payload to single-locale strings.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function localize(array $settings, string $locale, int $price): array
    {
        $formattedPrice = number_format($price);
        $pick = function (mixed $field) use ($locale, $formattedPrice): string {
            if (! is_array($field)) {
                return '';
            }
            $value = filled($field[$locale] ?? null) ? $field[$locale] : ($field['en'] ?? '');

            return str_replace('{price}', $formattedPrice, (string) $value);
        };

        $copy = [];
        foreach (array_keys(self::TEXT_FIELDS) as $key) {
            $copy[$key] = $pick($settings[$key] ?? null);
        }

        $copy['features'] = array_map(fn (array $row): array => [
            'icon' => (string) ($row['icon'] ?? 'sparkles'),
            'title' => $pick($row['title'] ?? null),
            'description' => $pick($row['description'] ?? null),
            'starter' => (bool) ($row['starter'] ?? false),
            'pro' => (bool) ($row['pro'] ?? true),
        ], $settings['features'] ?? []);

        $copy['trust_badges'] = array_map(fn (array $row): array => [
            'icon' => (string) ($row['icon'] ?? 'sparkles'),
            'text' => $pick($row['text'] ?? null),
        ], $settings['trust_badges'] ?? []);

        return $copy;
    }

    /**
     * Validation rules for the admin form.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $rules = [
            'price_etb' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'features' => ['required', 'array', 'min:1', 'max:10'],
            'features.*.icon' => ['required', 'string', Rule::in(self::ICONS)],
            'features.*.title' => ['required', 'array'],
            'features.*.title.en' => ['required', 'string', 'max:60'],
            'features.*.title.am' => ['nullable', 'string', 'max:60'],
            'features.*.description' => ['required', 'array'],
            'features.*.description.en' => ['nullable', 'string', 'max:120'],
            'features.*.description.am' => ['nullable', 'string', 'max:120'],
            'features.*.starter' => ['required', 'boolean'],
            'features.*.pro' => ['required', 'boolean'],
            'trust_badges' => ['present', 'array', 'max:3'],
            'trust_badges.*.icon' => ['required', 'string', Rule::in(self::ICONS)],
            'trust_badges.*.text' => ['required', 'array'],
            'trust_badges.*.text.en' => ['required', 'string', 'max:60'],
            'trust_badges.*.text.am' => ['nullable', 'string', 'max:60'],
        ];

        foreach (self::TEXT_FIELDS as $key => $max) {
            $rules[$key] = ['required', 'array'];
            $rules["{$key}.en"] = ['required', 'string', "max:{$max}"];
            $rules["{$key}.am"] = ['nullable', 'string', "max:{$max}"];
        }

        return $rules;
    }

    /**
     * @return array{en: string, am: string}
     */
    private static function text(string $en, string $am = ''): array
    {
        return ['en' => $en, 'am' => $am];
    }

    /**
     * @return array{icon: string, title: array{en: string, am: string}, description: array{en: string, am: string}, starter: bool, pro: bool}
     */
    private static function feature(string $icon, string $title, string $description): array
    {
        return [
            'icon' => $icon,
            'title' => self::text($title),
            'description' => self::text($description),
            'starter' => false,
            'pro' => true,
        ];
    }

    /**
     * @return array{icon: string, text: array{en: string, am: string}}
     */
    private static function badge(string $icon, string $text): array
    {
        return ['icon' => $icon, 'text' => self::text($text)];
    }
}
