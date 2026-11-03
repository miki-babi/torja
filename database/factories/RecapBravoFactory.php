<?php

namespace Database\Factories;

use App\Models\Recap;
use App\Models\RecapBravo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecapBravo>
 */
class RecapBravoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recap_id' => Recap::factory(),
            'user_id' => User::factory(),
        ];
    }
}
