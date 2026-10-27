<?php

namespace App\Models;

use App\Enums\WizardSection;
use Database\Factories\NutritionProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guest- or user-bound record of nutrition-wizard answers. Guests are bound
 * by session id until they register, at which point the profile is claimed by
 * the user. Each wizard section is stored as JSON in its own column.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property string $status
 * @property int $current_step
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $goal
 * @property array<string, mixed>|null $body
 * @property array<string, mixed>|null $activity
 * @property array<string, mixed>|null $pace
 * @property array<string, mixed>|null $diet
 * @property array<string, mixed>|null $liked_foods
 * @property array<string, mixed>|null $avoidances
 * @property array<string, mixed>|null $habits
 * @property array<string, mixed>|null $schedule
 * @property array<string, mixed>|null $budget
 * @property array<string, mixed>|null $cooking
 * @property array<string, mixed>|null $obstacles
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'session_id', 'status', 'current_step', 'completed_at', 'goal', 'body', 'activity', 'pace', 'diet', 'liked_foods', 'avoidances', 'habits', 'schedule', 'budget', 'cooking', 'obstacles'])]
class NutritionProfile extends Model
{
    /** @use HasFactory<NutritionProfileFactory> */
    use HasFactory;

    /**
     * The model's default attribute values (kept in sync with the migration
     * so freshly created instances report the right values without a
     * re-query).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
        'current_step' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'goal' => 'array',
            'body' => 'array',
            'activity' => 'array',
            'pace' => 'array',
            'diet' => 'array',
            'liked_foods' => 'array',
            'avoidances' => 'array',
            'habits' => 'array',
            'schedule' => 'array',
            'budget' => 'array',
            'cooking' => 'array',
            'obstacles' => 'array',
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
     * Whether any wizard section has been answered.
     */
    public function hasAnswers(): bool
    {
        foreach (WizardSection::cases() as $section) {
            if ($this->{$section->value} !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the wizard has been completed (step 13 submitted).
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * All saved section answers keyed by section column.
     *
     * @return array<string, array<string, mixed>>
     */
    public function answers(): array
    {
        $answers = [];

        foreach (WizardSection::cases() as $section) {
            $answers[$section->value] = $this->{$section->value} ?? [];
        }

        return $answers;
    }
}
