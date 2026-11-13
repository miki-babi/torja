<?php

namespace App\Models;

use Database\Factories\CheckInMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One attachment on a weekly check-in — a progress photo, a video, or a
 * voice note — stored on the public disk.
 *
 * @property int $id
 * @property int $check_in_id
 * @property string $path
 * @property string $kind
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['check_in_id', 'path', 'kind'])]
class CheckInMedia extends Model
{
    /** @use HasFactory<CheckInMediaFactory> */
    use HasFactory;

    /**
     * The check-in this file belongs to.
     *
     * @return BelongsTo<CheckIn, $this>
     */
    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class);
    }

    /**
     * The public URL to the stored file.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
