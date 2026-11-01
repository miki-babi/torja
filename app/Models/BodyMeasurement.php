<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One body-measurement check-in per user per day — the tape numbers behind
 * the progress page's measurements card.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $logged_on
 * @property string|null $waist_cm
 * @property string|null $chest_cm
 * @property string|null $arm_cm
 * @property string|null $hip_cm
 * @property string|null $thigh_cm
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'logged_on', 'waist_cm', 'chest_cm', 'arm_cm', 'hip_cm', 'thigh_cm'])]
class BodyMeasurement extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'waist_cm' => 'decimal:1',
            'chest_cm' => 'decimal:1',
            'arm_cm' => 'decimal:1',
            'hip_cm' => 'decimal:1',
            'thigh_cm' => 'decimal:1',
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
