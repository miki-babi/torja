<?php

namespace App\Models;

use Database\Factories\ProgramLeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A prospective client captured from a trainer's free-program share link —
 * contact details plus the onboarding questionnaire answers. `user_id`
 * links to the member account once the lead registers (claimed via the
 * `program_lead` session flag at post-auth).
 *
 * @property int $id
 * @property int $exercise_program_id
 * @property int $trainer_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property array<string, mixed> $answers
 * @property string $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['exercise_program_id', 'trainer_id', 'user_id', 'name', 'phone', 'answers', 'source'])]
class ProgramLead extends Model
{
    /** @use HasFactory<ProgramLeadFactory> */
    use HasFactory;

    /**
     * The questionnaire questions the public lead form asks, in order.
     * Values map to translation keys on the frontend.
     *
     * @var array<string, list<string>>
     */
    public const QUESTIONS = [
        'goal' => ['lose_fat', 'build_muscle', 'get_fitter', 'sport'],
        'experience' => ['beginner', 'intermediate', 'advanced'],
        'days_per_week' => ['1-2', '3-4', '5+'],
        'health' => ['none', 'injury', 'medical'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    /**
     * The free program this lead signed up for.
     *
     * @return BelongsTo<ExerciseProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'exercise_program_id');
    }

    /**
     * The coaching tenant that owns the lead.
     *
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    /**
     * The member account the lead registered as, once claimed.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the lead has registered a member account.
     */
    public function isConverted(): bool
    {
        return $this->user_id !== null;
    }
}
