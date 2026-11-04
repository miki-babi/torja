<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A member-pickable gym. `name_key` is the normalized (trimmed, lowered)
 * name used to dedupe creations; the creator moderates the gym's chat
 * room alongside app admins.
 *
 * @property int $id
 * @property string $name
 * @property string $name_key
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'name_key', 'latitude', 'longitude', 'created_by'])]
class Gym extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Normalize the dedupe key on write.
     */
    protected static function booted(): void
    {
        static::saving(function (Gym $gym): void {
            $gym->name_key = mb_strtolower(trim($gym->name));
        });
    }

    /**
     * The member who created the gym — moderates its chat room.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Members who picked this gym.
     *
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The gym's chat room.
     *
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
