<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\CheckInMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckInMedia>
 */
class CheckInMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'check_in_id' => CheckIn::factory(),
            'path' => 'check-ins/1/photo.jpg',
            'kind' => 'photo',
        ];
    }
}
