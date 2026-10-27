<?php

namespace App\Models;

use Database\Factories\FoodDiaryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One food-diary line logged by a member for a given day. Photos stay
 * client-side; `had_photos` records that the entry was captured visually.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $logged_on
 * @property string $name
 * @property string|null $detail
 * @property string|null $kcal
 * @property string|null $protein_g
 * @property string|null $carbs_g
 * @property string|null $fat_g
 * @property int|null $food_id
 * @property string $source
 * @property bool $had_photos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'logged_on', 'name', 'detail', 'kcal', 'protein_g', 'carbs_g', 'fat_g', 'food_id', 'source', 'had_photos'])]
class FoodDiaryEntry extends Model
{
    /** @use HasFactory<FoodDiaryEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_on' => 'date',
            'kcal' => 'decimal:1',
            'protein_g' => 'decimal:1',
            'carbs_g' => 'decimal:1',
            'fat_g' => 'decimal:1',
            'had_photos' => 'boolean',
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
     * @return BelongsTo<Food, $this>
     */
    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
