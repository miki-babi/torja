<?php

namespace App\Models;

use Database\Factories\ExercisePlaylistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One exercise entry inside an ExercisePlaylist. `exercise_id` matches the
 * frontend catalog id (e.g. "bench-press").
 *
 * @property int $id
 * @property int $exercise_playlist_id
 * @property string $exercise_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['exercise_playlist_id', 'exercise_id', 'position'])]
class ExercisePlaylistItem extends Model
{
    /** @use HasFactory<ExercisePlaylistItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ExercisePlaylist, $this>
     */
    public function playlist(): BelongsTo
    {
        return $this->belongsTo(ExercisePlaylist::class, 'exercise_playlist_id');
    }
}
