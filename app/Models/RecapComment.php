<?php

namespace App\Models;

use Database\Factories\RecapCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One comment on a recap post. Removable by the comment author or the
 * recap owner (who moderates their own thread).
 *
 * @property int $id
 * @property int $recap_id
 * @property int $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['recap_id', 'user_id', 'body'])]
class RecapComment extends Model
{
    /** @use HasFactory<RecapCommentFactory> */
    use HasFactory;

    /**
     * The commented recap.
     *
     * @return BelongsTo<Recap, $this>
     */
    public function recap(): BelongsTo
    {
        return $this->belongsTo(Recap::class);
    }

    /**
     * The commenting member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Feed payload for one viewer; `$viewerOwnsRecap` lets the recap owner
     * delete any comment on their post.
     *
     * @return array{id: int, body: string, created_at: string|null, user: array{id: int, name: string|null, avatar: string|null}, can_delete: bool}
     */
    public function toFeed(User $viewer, bool $viewerOwnsRecap): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar' => $this->user->avatar,
            ],
            'can_delete' => $viewerOwnsRecap || $this->user_id === $viewer->id,
        ];
    }
}
