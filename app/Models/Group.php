<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A member-created squad on the Ranks page. Members join via the shared
 * invite code; opted-in members are ranked by all-time volume.
 *
 * @property int $id
 * @property string $name
 * @property int $owner_id
 * @property string $invite_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'owner_id', 'invite_code'])]
class Group extends Model
{
    /**
     * Auto-generate a short unique invite code on create.
     */
    protected static function booted(): void
    {
        static::creating(function (Group $group): void {
            if ($group->invite_code === null || $group->invite_code === '') {
                do {
                    $code = Str::upper(Str::random(8));
                } while (static::where('invite_code', $code)->exists());

                $group->invite_code = $code;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withTimestamps();
    }
}
