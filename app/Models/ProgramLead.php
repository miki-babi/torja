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
     * Display labels for the preset question keys.
     *
     * @var array<string, string>
     */
    public const QUESTION_LABELS = [
        'goal' => "What's your main goal?",
        'experience' => 'Training experience',
        'days_per_week' => 'Days you can train per week',
        'health' => 'Any injuries or health concerns?',
    ];

    /**
     * Display labels for the preset option values.
     *
     * @var array<string, string>
     */
    public const OPTION_LABELS = [
        'lose_fat' => 'Lose fat',
        'build_muscle' => 'Build muscle',
        'get_fitter' => 'Get fitter',
        'sport' => 'Sport performance',
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        '1-2' => '1–2 days',
        '3-4' => '3–4 days',
        '5+' => '5+ days',
        'none' => 'None',
        'injury' => 'Recovering from injury',
        'medical' => 'Medical condition',
    ];

    /**
     * The default questionnaire as lead-form fields, used when a trainer
     * hasn't customized their claim form.
     *
     * @return list<array{key: string, label: string, type: string, options: list<array{value: string, label: string}>, required: bool}>
     */
    public static function presetFields(): array
    {
        return collect(self::QUESTIONS)
            ->map(fn (array $options, string $key): array => [
                'key' => $key,
                'label' => self::QUESTION_LABELS[$key] ?? $key,
                'type' => 'select',
                'options' => collect($options)
                    ->map(fn (string $value): array => [
                        'value' => $value,
                        'label' => self::OPTION_LABELS[$value] ?? $value,
                    ])
                    ->values()
                    ->all(),
                'required' => true,
            ])
            ->values()
            ->all();
    }

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
