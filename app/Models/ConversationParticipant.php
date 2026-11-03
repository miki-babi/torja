<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's seat in a conversation: join time, read cursor, mute flag and
 * the last moment we pushed a Telegram notification for the room.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int|null $last_read_message_id
 * @property Carbon|null $muted_at
 * @property Carbon|null $last_notified_at
 * @property Carbon $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['conversation_id', 'user_id', 'last_read_message_id', 'muted_at', 'last_notified_at', 'joined_at'])]
class ConversationParticipant extends Model
{
    protected $table = 'conversation_user';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_message_id' => 'integer',
            'muted_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * The room this seat belongs to.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The seated user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
