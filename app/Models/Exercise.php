<?php

namespace App\Models;

use App\Support\ExerciseCatalog;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One catalog exercise imported from the exercises dataset (1,324 rows).
 * Referenced by playlists and logged sets via its `slug`.
 *
 * @property int $id
 * @property string $source_id
 * @property string $slug
 * @property string $name
 * @property string|null $name_am
 * @property string $category
 * @property string $body_part
 * @property string $equipment
 * @property string $target
 * @property string $muscle_group
 * @property array<int, string> $secondary_muscles
 * @property string $instructions
 * @property array<int, string> $instruction_steps
 * @property string|null $instructions_am
 * @property array<int, string>|null $instruction_steps_am
 * @property string|null $translation_status
 * @property string $media_id
 * @property string $image_path
 * @property string $gif_path
 * @property string $attribution
 * @property string $exercise_type
 * @property string $level
 * @property string $location
 * @property string|null $movement_pattern
 * @property bool $compound
 * @property bool $isolation
 * @property bool $full_body
 * @property bool $women_home_friendly
 * @property bool $goal_fat_loss
 * @property bool $goal_muscle_gain
 * @property bool $review_needed
 * @property array<int, string> $review_reasons
 * @property Carbon|null $source_created_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $image_url
 * @property-read string $gif_url
 */
#[Fillable(['source_id', 'slug', 'name', 'name_am', 'category', 'body_part', 'equipment', 'target', 'muscle_group', 'secondary_muscles', 'instructions', 'instruction_steps', 'instructions_am', 'instruction_steps_am', 'translation_status', 'media_id', 'image_path', 'gif_path', 'attribution', 'exercise_type', 'level', 'location', 'movement_pattern', 'compound', 'isolation', 'full_body', 'women_home_friendly', 'goal_fat_loss', 'goal_muscle_gain', 'review_needed', 'review_reasons', 'source_created_at'])]
class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    /**
     * Flush the cached catalog lookup maps whenever exercises change.
     */
    protected static function booted(): void
    {
        static::saved(fn () => ExerciseCatalog::flush());
        static::deleted(fn () => ExerciseCatalog::flush());
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secondary_muscles' => 'array',
            'instruction_steps' => 'array',
            'instruction_steps_am' => 'array',
            'review_reasons' => 'array',
            'compound' => 'boolean',
            'isolation' => 'boolean',
            'full_body' => 'boolean',
            'women_home_friendly' => 'boolean',
            'goal_fat_loss' => 'boolean',
            'goal_muscle_gain' => 'boolean',
            'review_needed' => 'boolean',
            'source_created_at' => 'datetime',
        ];
    }

    /**
     * Public URL for the still thumbnail on the public disk.
     *
     * @return Attribute<string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->image_path));
    }

    /**
     * Public URL for the animated demo on the public disk.
     *
     * @return Attribute<string, never>
     */
    protected function gifUrl(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk('public')->url($this->gif_path));
    }

    /**
     * One-line summary like "Barbell · Pectorals".
     */
    public function detail(): string
    {
        return ucfirst($this->equipment).' · '.ucfirst($this->target);
    }

    /**
     * Localized display name: Amharic when the app locale is `am` and a
     * translation exists, English otherwise.
     */
    public function localizedName(): string
    {
        return app()->getLocale() === 'am' && $this->name_am !== null
            ? $this->name_am
            : $this->name;
    }

    /**
     * Localized instruction steps (Amharic when available).
     *
     * @return array<int, string>
     */
    public function localizedSteps(): array
    {
        return app()->getLocale() === 'am' && $this->instruction_steps_am !== null
            ? $this->instruction_steps_am
            : $this->instruction_steps;
    }

    /**
     * Lightweight card payload for lists, playlists and session screens.
     *
     * @return array{slug: string, name: string, detail: string, body_part: string, equipment: string, target: string, level: string, image_url: string}
     */
    public function toSummary(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->localizedName(),
            'detail' => $this->detail(),
            'body_part' => $this->body_part,
            'equipment' => $this->equipment,
            'target' => $this->target,
            'level' => $this->level,
            'image_url' => $this->image_url,
        ];
    }

    /**
     * Full payload for the exercise detail screen.
     *
     * @return array<string, mixed>
     */
    public function toDetail(): array
    {
        return $this->toSummary() + [
            'gif_url' => $this->gif_url,
            'muscle_group' => $this->muscle_group,
            'secondary_muscles' => $this->secondary_muscles,
            'instruction_steps' => $this->localizedSteps(),
            'location' => $this->location,
            'exercise_type' => $this->exercise_type,
            'movement_pattern' => $this->movement_pattern,
            'attribution' => $this->attribution,
        ];
    }
}
