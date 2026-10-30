<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One dispatch occurrence of a broadcast — a manual send, a scheduled
 * send, or a recurrence firing. Aggregate counters are incremented by the
 * delivery jobs so run stats survive partial failures.
 *
 * @property int $id
 * @property int $broadcast_id
 * @property string $trigger
 * @property int $recipients_count
 * @property int $sent_count
 * @property int $failed_count
 * @property int $blocked_count
 * @property int $deleted_count
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['broadcast_id', 'trigger', 'recipients_count', 'sent_count', 'failed_count', 'blocked_count', 'deleted_count', 'started_at', 'finished_at'])]
class TelegramBroadcastRun extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TelegramBroadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(TelegramBroadcast::class, 'broadcast_id');
    }

    /**
     * @return HasMany<TelegramBroadcastDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(TelegramBroadcastDelivery::class, 'run_id');
    }
}
