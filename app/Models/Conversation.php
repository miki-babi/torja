<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A chat room. `dm` rooms are keyed by the canonical `dm_key` pair
 * ("lowId:highId"); `group` and `gym` rooms are bound 1:1 to their
 * owning entity and created lazily/auto on membership.
 *
 * @property int $id
 * @property ConversationType $type
 * @property string|null $dm_key
 * @property int|null $group_id
 * @property int|null $gym_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'dm_key', 'group_id', 'gym_id'])]
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
     * The squad this room belongs to, when type is `group`.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The gym this room belongs to, when type is `gym`.
     *
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
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
     * Whether the user may delete others' messages here: squad owners and
     * gym creators moderate their rooms; admins moderate everywhere.
     */
    public function canModerate(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return match ($this->type) {
            ConversationType::Group => $this->group?->owner_id === $user->id,
            ConversationType::Gym => $this->gym?->created_by === $user->id,
            ConversationType::Dm => false,
        };
    }

    /**
     * The other participant in a DM room.
     */
    public function otherParticipant(User $user): ?User
    {
        if ($this->type !== ConversationType::Dm) {
            return null;
        }

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

    /**
     * Find or create the chat room for a squad.
     */
    public static function forGroup(Group $group): self
    {
        return static::where('group_id', $group->id)->first()
            ?? static::create(['group_id' => $group->id, 'type' => ConversationType::Group]);
    }

    /**
     * Find or create the chat room for a gym.
     */
    public static function forGym(Gym $gym): self
    {
        return static::where('gym_id', $gym->id)->first()
            ?? static::create(['gym_id' => $gym->id, 'type' => ConversationType::Gym]);
    }
}
