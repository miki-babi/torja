<?php

namespace App\Models;

use Database\Factories\ShopProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A sellable item in the Explore shop (gym equipment or supplements).
 * Listed publicly only while `published_at` is set.
 *
 * @property int $id
 * @property string $name
 * @property string|null $name_am
 * @property string|null $description
 * @property string|null $description_am
 * @property string|null $image_path
 * @property string $category
 * @property int $price_etb
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $image_url
 */
#[Fillable(['name', 'name_am', 'description', 'description_am', 'content', 'image_path', 'category', 'price_etb', 'published_at'])]
class ShopProduct extends Model
{
    /** @use HasFactory<ShopProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_etb' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ShopOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ShopOrder::class);
    }

    /**
     * Whether the product is listed in the Explore shop.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'am' && $this->name_am !== null
            ? $this->name_am
            : $this->name;
    }

    public function localizedDescription(): ?string
    {
        return app()->getLocale() === 'am' && $this->description_am !== null
            ? $this->description_am
            : $this->description;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path === null
            ? null
            : asset('storage/'.$this->image_path));
    }
}
