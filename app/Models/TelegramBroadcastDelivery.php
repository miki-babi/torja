<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single recipient's copy of one run. `message_ids` holds every
 * Telegram message id produced (albums yield several) so a sent
 * broadcast can later be recalled with deleteMessage.
 *
 * @property int $id
 * @property int $broadcast_id
 * @property int $run_id
 * @property int $user_id
 * @property string $chat_id
 * @property string $status
 * @property array<int, int>|null $message_ids
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $sent_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['broadcast_id', 'run_id', 'user_id', 'chat_id', 'status', 'message_ids', 'error', 'attempts', 'sent_at', 'deleted_at'])]
class TelegramBroadcastDelivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_ids' => 'array',
            'sent_at' => 'datetime',
            'deleted_at' => 'datetime',
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
     * @return BelongsTo<TelegramBroadcastRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(TelegramBroadcastRun::class, 'run_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
