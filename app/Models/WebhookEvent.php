<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A durably accepted provider webhook delivery. The unique
 * (provider, event_id) pair is what makes webhook processing
 * idempotent — at-least-once deliveries collapse into one row.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $type
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['provider', 'event_id', 'type', 'processed_at'])]
class WebhookEvent extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
