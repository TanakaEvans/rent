<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GradingRule>
 */
class GradingRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minThreshold = $this->faker->numberBetween(0, 90);
        $maxThreshold = $this->faker->numberBetween($minThreshold + 1, 100);

        return [
            'grading_scheme_id' => \App\Models\GradingScheme::factory(),
            'grade_label' => $this->faker->randomElement(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F']),
            'min_threshold' => $minThreshold,
            'max_threshold' => $maxThreshold,
            'grade_point' => $this->faker->randomFloat(2, 0, 4),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
