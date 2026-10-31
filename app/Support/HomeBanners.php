<?php

namespace App\Support;

use App\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin-managed ad banners for the member home carousel. Each banner is an
 * {image, url, cta} row: the image is an uploaded public-disk path or a
 * full URL, the url is an in-app path (`/...`) or an external http(s)
 * link, and cta is the pill label shown over the slide. An empty list
 * hides the carousel on the home screen.
 */
class HomeBanners
{
    public const SETTING_KEY = 'home_banners';

    /**
     * The stored banner rows as the admin saved them.
     *
     * @return array<int, array{image: string, url: string, cta: string}>
     */
    public static function banners(): array
    {
        $stored = Setting::get(self::SETTING_KEY, []);
        $rows = is_array($stored) ? ($stored['banners'] ?? []) : [];

        return array_values(array_filter(
            is_array($rows) ? $rows : [],
            fn (mixed $row): bool => is_array($row)
                && filled($row['image'] ?? null)
                && filled($row['url'] ?? null),
        ));
    }

    /**
     * Banners resolved for the member screen: storage paths become public
     * URLs and `external` marks links that leave the app.
     *
     * @return array<int, array{image: string, url: string, cta: string, external: bool}>
     */
    public static function forMember(): array
    {
        return array_map(
            fn (array $row): array => [
                'image' => self::imageUrl($row['image']),
                'url' => $row['url'],
                'cta' => (string) ($row['cta'] ?? ''),
                'external' => Str::startsWith($row['url'], 'http'),
            ],
            self::banners(),
        );
    }

    /**
     * Validation rules for the admin form.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'banners' => ['present', 'array', 'max:6'],
            'banners.*.image' => ['required', 'string', 'max:2048'],
            'banners.*.url' => [
                'required',
                'string',
                'max:2048',
                fn (string $attribute, mixed $value, Closure $fail) => self::validateUrl($value, $fail),
            ],
            'banners.*.cta' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * A banner link must be an in-app path or an http(s) URL — anything
     * else (javascript:, bare domains, ftp:) is rejected.
     */
    private static function validateUrl(mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The link must be a string.');

            return;
        }

        if (Str::startsWith($value, '/') && ! Str::startsWith($value, '//')) {
            return;
        }

        if (Str::startsWith($value, ['http://', 'https://']) && filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return;
        }

        $fail('The link must start with / or be a valid http(s) URL.');
    }

    /**
     * Resolve a stored image reference to a displayable URL.
     */
    private static function imageUrl(string $image): string
    {
        if (Str::startsWith($image, ['http://', 'https://', '/'])) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }
}
