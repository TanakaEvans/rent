<?php

namespace Database\Factories;

use App\Models\StudyLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyLevelFactory extends Factory
{
    protected $model = StudyLevel::class;

    public function definition(): array
    {
        $grade = $this->faker->numberBetween(1, 7);

        return [
            'name' => "Grade {$grade}",
            'code' => "GR{$grade}",
            'sequence' => $grade,
            'capacity' => $this->faker->numberBetween(30, 50),
            'description' => "Grade {$grade} study level",
        ];
    }

    /**
     * Create a specific grade level
     */
    public function grade(int $gradeNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "Grade {$gradeNumber}",
            'code' => "GR{$gradeNumber}",
            'sequence' => $gradeNumber,
            'capacity' => 40,
            'description' => "Grade {$gradeNumber} study level",
        ]);
    }
}
