<?php

namespace App\Models;

use Database\Factories\NutritionDatasetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Metadata for one imported version of the food database.
 *
 * @property int $id
 * @property string $version
 * @property array<int, string> $sources
 * @property array<string, mixed> $goal_tag_rules
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['version', 'sources', 'goal_tag_rules'])]
class NutritionDataset extends Model
{
    /** @use HasFactory<NutritionDatasetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'goal_tag_rules' => 'array',
        ];
    }
}
