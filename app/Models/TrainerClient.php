<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The trainer↔client link. A member can appear under several trainers (one
 * row each) when they joined through different coach bots or invite links.
 *
 * @property int $id
 * @property int $trainer_id
 * @property int $user_id
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['trainer_id', 'user_id', 'last_opened_at'])]
class TrainerClient extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trainer_clients';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
