<?php

namespace Database\Factories;

use App\Models\ClassGroup;
use App\Models\StudyLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassGroupFactory extends Factory
{
    protected $model = ClassGroup::class;

    public function definition(): array
    {
        $stream = $this->faker->randomElement(['A', 'B', 'C']);

        return [
            'study_level_id' => StudyLevel::factory(),
            'name' => "Class {$stream}",
            'code' => "CL{$stream}",
            'capacity' => $this->faker->numberBetween(25, 40),
            'description' => "Class group {$stream}",
            'status' => 'active',
        ];
    }

    /**
     * Create class for a specific study level
     */
    public function forLevel(StudyLevel $studyLevel): static
    {
        return $this->state(fn (array $attributes) => [
            'study_level_id' => $studyLevel->id,
        ]);
    }

    /**
     * Create a specific stream class (A, B, C, etc.)
     */
    public function stream(string $stream): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "Class {$stream}",
            'code' => "CL{$stream}",
            'description' => "Class group {$stream}",
        ]);
    }
}
