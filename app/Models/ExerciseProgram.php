<?php

namespace App\Models;

use Database\Factories\ExerciseProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An admin-authored exercise program: an ordered set of workouts a member can
 * claim. Claiming copies each workout into the member's account as an
 * ExercisePlaylist ("{program} — {workout}"). Free programs are claimable via
 * the share link; priced ones go through a Chapa payment.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $name_am
 * @property string|null $description
 * @property string|null $description_am
 * @property string|null $image_path
 * @property array<int, string>|null $highlights
 * @property array<int, string>|null $highlights_am
 * @property int $price_etb
 * @property string|null $share_token
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url
 */
#[Fillable(['user_id', 'name', 'name_am', 'description', 'description_am', 'image_path', 'highlights', 'highlights_am', 'price_etb', 'share_token', 'published_at'])]
class ExerciseProgram extends Model
{
    /** @use HasFactory<ExerciseProgramFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'highlights_am' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Public URL for the sales-page cover image on the public disk, or null
     * when no image was uploaded.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path));
    }

    /**
     * The admin who authored the program.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The program's workouts, in program order.
     *
     * @return HasMany<ExerciseProgramWorkout, $this>
     */
    public function workouts(): HasMany
    {
        return $this->hasMany(ExerciseProgramWorkout::class)->orderBy('position');
    }

    /**
     * Member claim records — one per member who saved or bought the program.
     *
     * @return HasMany<ExerciseProgramAccess, $this>
     */
    public function accessGrants(): HasMany
    {
        return $this->hasMany(ExerciseProgramAccess::class);
    }

    /**
     * Whether the program can be claimed without a payment.
     */
    public function isFree(): bool
    {
        return $this->price_etb === 0;
    }

    /**
     * Whether the program is listed in the public /explore catalog.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
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
     * Localized description (Amharic when available).
     */
    public function localizedDescription(): ?string
    {
        return app()->getLocale() === 'am' && $this->description_am !== null
            ? $this->description_am
            : $this->description;
    }

    /**
     * Localized highlight bullets — falls back to the English list when no
     * Amharic set exists.
     *
     * @return array<int, string>
     */
    public function localizedHighlights(): array
    {
        if (app()->getLocale() === 'am' && $this->highlights_am !== null) {
            return $this->highlights_am;
        }

        return $this->highlights ?? [];
    }

    /**
     * The public shared-program URL, or null when the program is not shared.
     * Carries `ref` + utm params so program distribution is attributed.
     */
    public function shareUrl(): ?string
    {
        if ($this->share_token === null) {
            return null;
        }

        $url = route('programs.shared.show', $this->share_token).'?'.Arr::query([
            'ref' => (string) $this->user_id,
            'utm_source' => 'program',
            'utm_medium' => 'share',
            'utm_content' => 'exercise_program',
        ]);

        return ShortLink::for($this, $url)->shortUrl();
    }
}
