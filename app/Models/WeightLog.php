<?php

namespace App\Models;

use Database\Factories\WeightLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One self-reported body-weight check-in per user per day.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $logged_on
 * @property string $weight_kg
 * @property string $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'logged_on', 'weight_kg', 'source'])]
class WeightLog extends Model
{
    /** @use HasFactory<WeightLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'weight_kg' => 'decimal:1',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
