<?php

namespace App\Models;

use Database\Factories\TrainerPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A time-based coaching offer a trainer sells on their public page — e.g.
 * "1:1 coaching, 30 days". Buying one creates a ClientPlan subscription
 * through a Jami payment.
 *
 * @property int $id
 * @property int $trainer_id
 * @property string $name
 * @property string|null $description
 * @property string $kind
 * @property int $price_etb
 * @property int $duration_days
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_id', 'name', 'description', 'kind', 'price_etb', 'duration_days', 'is_active'])]
class TrainerPlan extends Model
{
    /** @use HasFactory<TrainerPlanFactory> */
    use HasFactory;

    /**
     * The plan kinds a trainer can offer.
     *
     * @var array<string, string>
     */
    public const KINDS = [
        'one_on_one' => 'One-on-one coaching',
        'group' => 'Group program',
        'check_in' => 'Check-in only',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The coaching tenant selling this plan.
     *
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * Client subscriptions purchased against this plan.
     *
     * @return HasMany<ClientPlan, $this>
     */
    public function clientPlans(): HasMany
    {
        return $this->hasMany(ClientPlan::class);
    }
}
