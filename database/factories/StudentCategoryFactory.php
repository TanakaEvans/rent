<?php

namespace Database\Factories;

use App\Models\StudentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentCategoryFactory extends Factory
{
    protected $model = StudentCategory::class;

    public function definition(): array
    {
        $categories = [
            ['name' => 'Day Scholar', 'code' => 'DAY'],
            ['name' => 'Boarder', 'code' => 'BRD'],
            ['name' => 'Half Boarder', 'code' => 'HBD'],
            ['name' => 'Special Needs', 'code' => 'SPN'],
            ['name' => 'Scholarship', 'code' => 'SCH'],
        ];

        $category = $this->faker->randomElement($categories);

        return [
            'name' => $category['name'],
            'code' => $category['code'],
            'description' => "Students under the {$category['name']} category",
            'is_active' => true,
        ];
    }

    /**
     * Create a specific category type
     */
    public function type(string $name, string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'code' => $code,
            'description' => "Students under the {$name} category",
            'is_active' => true,
        ]);
    }

    /**
     * Inactive category
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
