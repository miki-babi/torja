<?php

namespace App\Models;

use Database\Factories\TrainerLeadFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question on a trainer's customizable claim form — the questionnaire a
 * guest fills before taking a free shared program. `type` is `text`,
 * `textarea` or `select`; `options` only applies to selects.
 *
 * @property int $id
 * @property int $trainer_id
 * @property string $label
 * @property string $type
 * @property array<int, string>|null $options
 * @property bool $required
 * @property int $position
 */
#[Fillable(['trainer_id', 'label', 'type', 'options', 'required', 'position'])]
class TrainerLeadField extends Model
{
    /** @use HasFactory<TrainerLeadFieldFactory> */
    use HasFactory;

    public const TYPES = ['text', 'textarea', 'select'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
        ];
    }

    /**
     * The trainer who authored this question.
     *
     * @return BelongsTo<Trainer, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }
}
