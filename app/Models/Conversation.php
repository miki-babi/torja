<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A chat room. `dm` rooms are keyed by the canonical `dm_key` pair
 * ("lowId:highId") — in the coach-scoped app the only room a member can
 * open is the DM with their coach.
 *
 * @property int $id
 * @property ConversationType $type
 * @property string|null $dm_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'dm_key'])]
class Conversation extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => ConversationType::class];
    }

    /**
     * Room members.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot(['last_read_message_id', 'muted_at', 'joined_at', 'last_notified_at'])
            ->withTimestamps();
    }

    /**
     * Participant rows for read/mute/notify bookkeeping.
     *
     * @return HasMany<ConversationParticipant, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Messages in the room — callers pick their own ordering (latest for
     * cursor pages, chronological on render).
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Whether the user is a member of this room.
     */
    public function isParticipant(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->exists();
    }

    /**
     * The user's participant row, if any.
     */
    public function membershipFor(User $user): ?ConversationParticipant
    {
        return $this->memberships()->where('user_id', $user->id)->first();
    }

    /**
     * Add the user to the room without disturbing an existing membership.
     */
    public function addParticipant(User $user): void
    {
        ConversationParticipant::firstOrCreate(
            ['conversation_id' => $this->id, 'user_id' => $user->id],
            ['joined_at' => now()],
        );
    }

    /**
     * Remove the user from the room.
     */
    public function removeParticipant(User $user): void
    {
        $this->memberships()->where('user_id', $user->id)->delete();
    }

    /**
     * Whether the user may delete others' messages here — admins only.
     */
    public function canModerate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether this room is the member's DM with their active coach — the
     * only thread a member may open in the coach-scoped app.
     */
    public function isCoachThreadFor(User $user): bool
    {
        return $this->otherParticipant($user)?->id === $user->activeCoach()?->user_id;
    }

    /**
     * The other participant in a DM room.
     */
    public function otherParticipant(User $user): ?User
    {
        return $this->participants()->where('users.id', '!=', $user->id)->first();
    }

    /**
     * Find or create the DM room between two users.
     */
    public static function dmBetween(User $a, User $b): self
    {
        $key = implode(':', collect([$a->id, $b->id])->sort()->all());

        $conversation = static::where('dm_key', $key)->first()
            ?? static::create(['dm_key' => $key, 'type' => ConversationType::Dm]);

        $conversation->addParticipant($a);
        $conversation->addParticipant($b);

        return $conversation;
    }
}
