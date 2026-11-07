<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A text broadcast a trainer sent to all linked clients through their own
 * Telegram bot. Delivery counters are tallied by SendTrainerBroadcast.
 *
 * @property int $id
 * @property int $trainer_id
 * @property string $body
 * @property int $sent_count
 * @property int $failed_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_id', 'body', 'sent_count', 'failed_count'])]
class TrainerBroadcast extends Model
{
    use HasFactory;

    /**
     * The trainer who sent this broadcast.
     *
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
}
