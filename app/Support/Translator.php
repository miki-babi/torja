<?php

namespace App\Support;

use App\Models\LanguageLine;
use Illuminate\Support\Facades\Cache;

/**
 * Read API over language_lines: flat 'group.key' => text maps per locale for
 * the Inertia translations prop, plus single-key lookup with fallback
 * (requested locale -> default -> 'group.key').
 */
class Translator
{
    private const CACHE_PREFIX = 'translator.map.';

    /**
     * Flat map of every translated string for one locale.
     *
     * @return array<string, string>
     */
    public static function map(string $locale): array
    {
        return Cache::memo()->rememberForever(
            self::CACHE_PREFIX.$locale,
            fn (): array => LanguageLine::query()
                ->get(['group', 'key', 'text'])
                ->mapWithKeys(function (LanguageLine $line) use ($locale): array {
                    $value = $line->text[$locale]
                        ?? $line->text[Locales::defaultCode()]
                        ?? null;

                    return $value === null
                        ? []
                        : ["{$line->group}.{$line->key}" => $value];
                })
                ->all(),
        );
    }

    /**
     * One string with fallback: locale -> default -> 'group.key' literal.
     */
    public static function t(string $group, string $key, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return self::map($locale)["{$group}.{$key}"] ?? "{$group}.{$key}";
    }

    /**
     * Drop every cached locale map (after admin writes or seeding).
     */
    public static function flush(): void
    {
        foreach (Locales::codes() as $code) {
            Cache::forget(self::CACHE_PREFIX.$code);
        }
    }
}
