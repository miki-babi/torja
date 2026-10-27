<?php

namespace Database\Factories;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3, false);

        return [
            'source_id' => fake()->unique()->numerify('####'),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'name' => $name,
            'name_am' => fake()->word(),
            'category' => fake()->randomElement(['chest', 'back', 'waist', 'upper legs']),
            'body_part' => 'chest',
            'equipment' => 'body weight',
            'target' => 'pectorals',
            'muscle_group' => 'chest',
            'secondary_muscles' => ['triceps'],
            'instructions' => fake()->paragraph(),
            'instruction_steps' => [fake()->sentence(), fake()->sentence()],
            'instructions_am' => fake()->paragraph(),
            'instruction_steps_am' => [fake()->sentence()],
            'translation_status' => 'machine_unreviewed',
            'media_id' => fake()->bothify('??###??'),
            'image_path' => 'exercises/images/placeholder.jpg',
            'gif_path' => 'exercises/videos/placeholder.gif',
            'attribution' => '© Gym visual — https://gymvisual.com/',
            'exercise_type' => 'strength',
            'level' => 'intermediate',
            'location' => 'home',
            'movement_pattern' => 'push',
            'compound' => fake()->boolean(),
            'isolation' => fake()->boolean(),
            'full_body' => false,
            'women_home_friendly' => true,
            'goal_fat_loss' => fake()->boolean(),
            'goal_muscle_gain' => fake()->boolean(),
            'review_needed' => false,
            'review_reasons' => [],
            'source_created_at' => now(),
        ];
    }

    /**
     * Pin the exercise to a body part (and matching category).
     */
    public function forBodyPart(string $bodyPart): static
    {
        return $this->state([
            'body_part' => $bodyPart,
            'category' => $bodyPart,
        ]);
    }
}
