<?php

namespace App\Models;

use Database\Factories\LanguageLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One translatable UI string. `text` is a JSON map of locale code => string,
 * so new languages slot in without schema changes.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property array<string, string> $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['group', 'key', 'text'])]
class LanguageLine extends Model
{
    /** @use HasFactory<LanguageLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'text' => 'array',
        ];
    }
}
