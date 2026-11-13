<?php

namespace Database\Factories;

use App\Models\Trainer;
use App\Models\TrainerLeadField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainerLeadField>
 */
class TrainerLeadFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trainer_id' => Trainer::factory(),
            'label' => fake()->sentence(3),
            'type' => 'text',
            'options' => null,
            'required' => true,
            'position' => 0,
        ];
    }

    /**
     * A select-type question with options.
     */
    public function select(array $options = ['Yes', 'No']): static
    {
        return $this->state(fn (): array => [
            'type' => 'select',
            'options' => $options,
        ]);
    }
}
