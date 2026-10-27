<?php

namespace Database\Factories;

use App\Models\LanguageLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LanguageLine>
 */
class LanguageLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'common',
            'key' => fake()->unique()->slug(2),
            'text' => ['en' => fake()->sentence()],
        ];
    }
}
