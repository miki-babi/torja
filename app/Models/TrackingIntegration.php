<?php

namespace App\Models;

use App\Support\Tracking\TrackingScope;
use Database\Factories\TrackingIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * An admin-managed third-party tracking integration (e.g. a Meta pixel).
 * Enabled integrations matching the current route scope are shared to the
 * frontend, which injects the provider's script and fires mapped events.
 *
 * @property int $id
 * @property string $provider
 * @property string $name
 * @property string $external_id
 * @property array<string, mixed>|null $config
 * @property array<int, string> $scopes
 * @property array<string, string>|null $event_map
 * @property bool $is_enabled
 * @property bool $consent_required
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['provider', 'name', 'external_id', 'config', 'scopes', 'event_map', 'is_enabled', 'consent_required'])]
class TrackingIntegration extends Model
{
    /** @use HasFactory<TrackingIntegrationFactory> */
    use HasFactory;

    /**
     * Cache key prefix for the enabled-integrations-per-scope lookup shared
     * with the frontend. Bumped whenever an integration changes.
     */
    public const CACHE_KEY = 'tracking_integrations:enabled';

    /**
     * Flush the shared-props cache whenever integrations change.
     */
    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'scopes' => 'array',
            'event_map' => 'array',
            'is_enabled' => 'boolean',
            'consent_required' => 'boolean',
        ];
    }

    /**
     * Enabled integrations for the given route scope — only the fields the
     * browser needs; secrets in `config` are never serialized here.
     *
     * @return array<int, array{id: int, provider: string, name: string, external_id: string, event_map: array<string, string>, consent_required: bool, snippet: string|null}>
     */
    public static function frontendPayload(?string $routeName): array
    {
        $scope = app(TrackingScope::class)->for($routeName);

        $rows = Cache::remember(self::CACHE_KEY, 60, fn (): array => self::query()
            ->where('is_enabled', true)
            ->get(['id', 'provider', 'name', 'external_id', 'scopes', 'event_map', 'consent_required', 'config'])
            ->map(fn (self $i): array => $i->getAttributes())
            ->all());

        $integrations = self::hydrate($rows);

        return $integrations
            ->filter(fn (self $i): bool => in_array('all', $i->scopes ?? [], true)
                || ($scope !== null && in_array($scope, $i->scopes ?? [], true)))
            ->map(fn (self $i): array => [
                'id' => $i->id,
                'provider' => $i->provider,
                'name' => $i->name,
                'external_id' => $i->external_id,
                'event_map' => $i->event_map ?? [],
                'consent_required' => $i->consent_required,
                'snippet' => $i->config['snippet'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * The Meta standard/custom event a tracked event maps to, if any.
     */
    public function metaEventFor(string $eventName): ?string
    {
        return $this->event_map[$eventName] ?? null;
    }

    /**
     * Merge admin-submitted values into the JSON config. The access token
     * stays encrypted-at-rest and is only replaced when a new value is
     * given; the raw script snippet is plain text (it executes in the
     * browser anyway) and an empty value removes it.
     */
    public function updateConfig(?string $accessToken, ?string $snippet): void
    {
        $config = $this->config ?? [];

        if (is_string($accessToken) && $accessToken !== '') {
            $config['access_token'] = Crypt::encryptString($accessToken);
        }

        if (is_string($snippet) && $snippet !== '') {
            $config['snippet'] = $snippet;
        } else {
            unset($config['snippet']);
        }

        $this->update(['config' => $config === [] ? null : $config]);
    }

    /**
     * Store a CAPI access token encrypted-at-rest inside the JSON config.
     * Kept as a per-value ciphertext because the `config` column itself is
     * JSON and cannot hold the whole-column encrypted blob.
     */
    public function setAccessToken(string $plainToken): void
    {
        $config = $this->config ?? [];
        $config['access_token'] = Crypt::encryptString($plainToken);

        $this->update(['config' => $config]);
    }

    /**
     * The decrypted CAPI access token, or null when unset/undecryptable.
     */
    public function accessToken(): ?string
    {
        $encrypted = $this->config['access_token'] ?? null;

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }
}
