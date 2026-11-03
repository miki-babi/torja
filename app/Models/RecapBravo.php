<?php

namespace App\Models;

use Database\Factories\RecapBravoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One bravo (kudos) by one member on one recap — the unique key makes
 * toggling idempotent, same shape as message reactions.
 *
 * @property int $id
 * @property int $recap_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['recap_id', 'user_id'])]
class RecapBravo extends Model
{
    /** @use HasFactory<RecapBravoFactory> */
    use HasFactory;

    /**
     * The reacted recap.
     *
     * @return BelongsTo<Recap, $this>
     */
    public function recap(): BelongsTo
    {
        return $this->belongsTo(Recap::class);
    }

    /**
     * The member who bravoed.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
