<?php

namespace Database\Factories;

use App\Models\ShortLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortLink>
 */
class ShortLinkFactory extends Factory
{
    /** @var class-string<ShortLink> */
    protected $model = ShortLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => ShortLink::generateCode(),
            'url' => fake()->url(),
            'clicks' => 0,
        ];
    }
}
