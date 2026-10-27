<?php

namespace App\Models;

use Database\Factories\ShortLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A short shareable code that redirects to a longer destination URL — used
 * for program and playlist share links so they stay copy-friendly.
 *
 * @property int $id
 * @property string $code
 * @property string $url
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property int|null $user_id
 * @property int $clicks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'url', 'linkable_type', 'linkable_id', 'user_id', 'clicks'])]
class ShortLink extends Model
{
    /** @use HasFactory<ShortLinkFactory> */
    use HasFactory;

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mint a fresh unambiguous code (no ambiguous-looking prefix).
     */
    public static function generateCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * The short URL visitors should be given.
     */
    public function shortUrl(): string
    {
        return route('links.resolve', $this->code);
    }

    /**
     * Drop any short link pointing at a model — e.g. when it is unshared.
     */
    public static function forget(Model $linkable): void
    {
        static::query()->whereMorphedTo('linkable', $linkable)->delete();
    }

    /**
     * Find or mint the short link for a model's destination URL — one link
     * per resource, so sharing twice yields the same code.
     */
    public static function for(Model $linkable, string $url): self
    {
        return static::query()->firstOrCreate(
            [
                'linkable_type' => $linkable->getMorphClass(),
                'linkable_id' => $linkable->getKey(),
            ],
            [
                'code' => static::generateCode(),
                'url' => $url,
                'user_id' => $linkable->getAttribute('user_id'),
            ],
        );
    }
}
