<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentMark>
 */
class StudentMarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => \App\Models\Assessment::factory(),
            'student_id' => 1, // Default student ID
            'marks_obtained' => $this->faker->optional(0.9)->randomFloat(2, 0, 100), // 90% chance of having marks
            'calculated_grade' => $this->faker->optional()->randomElement(['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F']),
            'grade_point' => $this->faker->optional()->randomFloat(2, 0, 4),
            'status' => $this->faker->randomElement(['present', 'absent', 'exempt']),
            'remarks' => $this->faker->optional()->sentence(),
            'entered_by' => null,
            'entered_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
