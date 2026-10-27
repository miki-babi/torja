<?php

namespace App\Models;

use Database\Factories\TrackedEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One recorded product interaction. Append-only: rows are never updated
 * except to attribute guest events to a user after login/registration.
 *
 * @property int $id
 * @property string $event_uid
 * @property string|null $visitor_id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property string $name
 * @property string|null $route_name
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property array<string, mixed>|null $context
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
#[Fillable(['event_uid', 'visitor_id', 'user_id', 'session_id', 'name', 'route_name', 'subject_type', 'subject_id', 'properties', 'context', 'occurred_at'])]
class TrackedEvent extends Model
{
    /** @use HasFactory<TrackedEventFactory> */
    use HasFactory;

    /**
     * Events are append-only; there is no updated_at column.
     */
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
