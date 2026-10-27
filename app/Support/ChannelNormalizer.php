<?php

namespace App\Support;

/**
 * Maps a raw attribution payload (utm params, click ids, ref, referrer) to a
 * stable channel label for funnel reporting: explicit utm_source wins, then
 * member share links (`ref`), then referrer host mapping, then click ids,
 * then `direct`.
 */
class ChannelNormalizer
{
    /**
     * Referrer host substring → channel label. First match wins.
     *
     * @var array<string, string>
     */
    private const HOST_MAP = [
        'instagram.com' => 'instagram',
        'l.instagram.com' => 'instagram',
        'tiktok.com' => 'tiktok',
        'facebook.com' => 'facebook',
        'fb.com' => 'facebook',
        't.co' => 'x',
        'twitter.com' => 'x',
        'x.com' => 'x',
        't.me' => 'telegram',
        'telegram' => 'telegram',
        'wa.me' => 'whatsapp',
        'whatsapp.com' => 'whatsapp',
        'youtube.com' => 'youtube',
        'youtu.be' => 'youtube',
        'google.' => 'google',
        'bing.com' => 'bing',
    ];

    /**
     * Normalize a tracked-event `context.attribution` payload (or
     * `users.attribution`) to a channel label.
     *
     * @param  array<string, mixed>|null  $attribution
     */
    public static function channel(?array $attribution): string
    {
        if ($attribution === null || $attribution === []) {
            return 'direct';
        }

        $utmSource = $attribution['utm_source'] ?? null;

        if (is_string($utmSource) && $utmSource !== '') {
            return strtolower($utmSource);
        }

        if (! empty($attribution['ref'])) {
            return 'member_share';
        }

        $referrer = $attribution['referrer'] ?? null;

        if (is_string($referrer) && $referrer !== '') {
            $host = parse_url($referrer, PHP_URL_HOST);

            if (is_string($host)) {
                $host = strtolower($host);

                foreach (self::HOST_MAP as $needle => $channel) {
                    if (str_contains($host, $needle)) {
                        return $channel;
                    }
                }

                return 'referral:'.$host;
            }
        }

        foreach (['fbclid', 'gclid', 'ttclid'] as $clickId) {
            if (! empty($attribution[$clickId])) {
                return match ($clickId) {
                    'fbclid' => 'facebook',
                    'gclid' => 'google',
                    default => 'tiktok',
                };
            }
        }

        return 'direct';
    }
}
