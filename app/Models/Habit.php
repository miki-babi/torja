<?php

namespace App\Models;

use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'position'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<HabitLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    /**
     * Frontend payload: identity, log dates, current streak and total.
     *
     * @return array{id: int, name: string, dates: array<int, string>, streak: int, total: int}
     */
    public function toFrontend(): array
    {
        $dates = $this->logs
            ->map(fn (HabitLog $log) => $log->date->toDateString())
            ->sort()
            ->values()
            ->all();

        $lookup = array_fill_keys($dates, true);
        $streak = 0;
        $day = today()->copy();

        // If today isn't done yet, the streak counts from yesterday.
        if (! isset($lookup[$day->toDateString()])) {
            $day = $day->subDay();
        }
        while (isset($lookup[$day->toDateString()])) {
            $streak++;
            $day = $day->subDay();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'dates' => $dates,
            'streak' => $streak,
            'total' => count($dates),
        ];
    }
}
