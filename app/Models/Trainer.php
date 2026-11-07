<?php

namespace App\Models;

use Database\Factories\TrainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A coaching tenant: the trainer-facing account that owns a client roster,
 * authored programs, and (optionally) a dedicated Telegram bot whose mini
 * app is the client-facing surface. Clients are ordinary users linked
 * through the `trainer_clients` pivot.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string|null $brand_name
 * @property string|null $bot_token
 * @property string|null $bot_username
 * @property string|null $mini_app_name
 * @property string|null $webhook_secret
 * @property Carbon|null $webhook_registered_at
 * @property Carbon|null $menu_button_set_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 */
#[Fillable(['user_id', 'slug', 'brand_name', 'bot_token', 'bot_username', 'mini_app_name', 'webhook_secret', 'webhook_registered_at', 'menu_button_set_at'])]
#[Hidden(['bot_token', 'webhook_secret'])]
class Trainer extends Model
{
    /** @use HasFactory<TrainerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'webhook_registered_at' => 'datetime',
            'menu_button_set_at' => 'datetime',
        ];
    }

    /**
     * The login account behind this trainer.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Members linked to this trainer as clients.
     *
     * @return BelongsToMany<User, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trainer_clients')
            ->withTimestamps();
    }

    /**
     * Text broadcasts this trainer sent to clients.
     *
     * @return HasMany<TrainerBroadcast, $this>
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(TrainerBroadcast::class);
    }

    /**
     * Exercise programs authored by the trainer's account.
     *
     * @return HasMany<ExerciseProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(ExerciseProgram::class, 'user_id', 'user_id');
    }

    /**
     * Meal programs authored by the trainer's account.
     *
     * @return HasMany<MealProgram, $this>
     */
    public function mealPrograms(): HasMany
    {
        return $this->hasMany(MealProgram::class, 'user_id', 'user_id');
    }

    /**
     * The name clients see — the brand name when set, else the account name.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->brand_name
            ?? $this->user?->name
            ?? 'Coach');
    }

    /**
     * Whether a Telegram bot token has been connected.
     */
    public function hasConnectedBot(): bool
    {
        return $this->bot_token !== null;
    }

    /**
     * The public mini-app/invite URL clients use to reach this trainer.
     */
    public function miniAppUrl(): string
    {
        return url("/t/{$this->slug}");
    }

    /**
     * The Telegram webhook URL this trainer's bot should post updates to.
     */
    public function webhookUrl(): string
    {
        return route('telegram.tenant.webhook', $this->slug);
    }

    /**
     * A unique tenant slug derived from a display name — `<name>-<random>`.
     */
    public static function mintSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'coach';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (static::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
