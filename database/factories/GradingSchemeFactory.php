<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradingScheme>
 */
class GradingSchemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Grading Scheme',
            'type' => $this->faker->randomElement(['letter', 'percentage', 'units', 'custom']),
            'academic_year_id' => null,
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Indicate that the grading scheme is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the grading scheme is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a letter-based grading scheme.
     */
    public function letterBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'letter',
            'name' => 'Letter Grade Scheme',
        ]);
    }

    /**
     * Create a percentage-based grading scheme.
     */
    public function percentageBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'name' => 'Percentage Grade Scheme',
        ]);
    }
}
