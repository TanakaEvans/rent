<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['exam', 'test', 'quiz', 'assignment', 'project']),
            'subject_id' => 1, // Default subject ID
            'grade_level_id' => 1, // Default grade level ID
            'class_id' => null,
            'grading_scheme_id' => \App\Models\GradingScheme::factory(),
            'academic_year_id' => 1, // Default academic year ID
            'term_id' => null,
            'total_marks' => $this->faker->numberBetween(50, 200),
            'weightage' => $this->faker->randomFloat(2, 10, 100),
            'due_date' => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
            'instructions' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'active', 'completed', 'archived']),
            'created_by' => null,
        ];
    }
}
