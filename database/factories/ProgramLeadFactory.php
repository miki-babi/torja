<?php

namespace Database\Factories;

use App\Models\ExerciseProgram;
use App\Models\ProgramLead;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramLead>
 */
class ProgramLeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exercise_program_id' => ExerciseProgram::factory(),
            'trainer_id' => Trainer::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'phone' => '09'.fake()->numerify('########'),
            'answers' => [
                'goal' => 'lose_fat',
                'experience' => 'beginner',
                'days_per_week' => '3-4',
                'health' => 'none',
            ],
            'source' => 'public_page',
        ];
    }
}
