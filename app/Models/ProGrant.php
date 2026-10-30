<?php

namespace App\Models;

use Database\Factories\ProGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Pro membership granted without payment — earned by hitting the
 * ambassador referral milestone (`source = 'referral_reward'`) or given
 * out manually by an admin (`source = 'manual'`). `referral_count`
 * snapshots the referrer's signup count at grant time. Revoked rows stay
 * for audit; an active grant is one with `revoked_at = null`. Paid Pro
 * lives on `payments` instead — see `User::isPaidPro()`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $source
 * @property int|null $referral_count
 * @property int|null $granted_by_id
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_id
 * @property string|null $revoke_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'source', 'referral_count', 'granted_by_id', 'revoked_at', 'revoked_by_id', 'revoke_reason'])]
class ProGrant extends Model
{
    /** @use HasFactory<ProGrantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Only grants that are currently in effect.
     *
     * @param  Builder<ProGrant>  $query
     * @return Builder<ProGrant>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * The member holding this grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who granted it manually, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    /**
     * The admin who revoked it, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_id');
    }

    /**
     * Whether the grant currently unlocks Pro.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Revoke the grant — admin takedown of an earned or gifted Pro.
     */
    public function revoke(User $admin, ?string $reason = null): void
    {
        if (! $this->isActive()) {
            return;
        }

        $this->update([
            'revoked_at' => now(),
            'revoked_by_id' => $admin->id,
            'revoke_reason' => $reason,
        ]);
    }
}
