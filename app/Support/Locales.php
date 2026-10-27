<?php

namespace App\Support;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read API over the languages table — cached list of active languages and the
 * default code, used by the locale middleware and shared props.
 */
class Locales
{
    private const CACHE_KEY = 'locales.active';

    /**
     * Active languages ordered by position.
     *
     * Raw attribute arrays are cached (serialization-safe), then
     * rehydrated into models so callers get a real Collection<Language>.
     *
     * @return Collection<int, Language>
     */
    public static function active(): Collection
    {
        $rows = Cache::memo()->rememberForever(
            self::CACHE_KEY,
            fn (): array => Language::query()
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('code')
                ->get()
                ->map(fn (Language $l): array => $l->getAttributes())
                ->all(),
        );

        return Language::hydrate($rows);
    }

    /**
     * The default language code ('en' fallback).
     */
    public static function defaultCode(): string
    {
        $language = self::active()->firstWhere('is_default', true)
            ?? self::active()->first();

        return $language instanceof Language ? $language->code : 'en';
    }

    /**
     * Whether the code belongs to an active language.
     */
    public static function isActive(?string $code): bool
    {
        return $code !== null && self::active()->contains('code', $code);
    }

    /**
     * Active codes for validation rules.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return self::active()->pluck('code')->all();
    }

    /**
     * Drop the cached language list (after admin writes or seeding).
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
