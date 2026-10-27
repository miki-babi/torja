<?php

namespace Database\Factories;

use App\Models\ExerciseProgram;
use App\Models\ExerciseProgramAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProgramAccess>
 */
class ExerciseProgramAccessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ExerciseProgramAccess>
     */
    protected $model = ExerciseProgramAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_program_id' => ExerciseProgram::factory(),
            'payment_id' => null,
        ];
    }
}
