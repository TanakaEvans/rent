<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicPeriodFactory extends Factory
{
    protected $model = AcademicPeriod::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2023, 2025);
        $type = $this->faker->randomElement(['year', 'semester', 'term']);

        return [
            'name' => "Academic Year {$year}",
            'code' => "AY{$year}",
            'type' => $type,
            'start_date' => "{$year}-01-15",
            'end_date' => "{$year}-12-10",
            'status' => $this->faker->randomElement(['active', 'inactive', 'upcoming']),
            'description' => "Academic period for the year {$year}",
        ];
    }

    /**
     * State for current active period
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Academic Year 2025',
            'code' => 'AY2025',
            'type' => 'year',
            'start_date' => '2025-01-15',
            'end_date' => '2025-12-10',
            'status' => 'active',
            'description' => 'Current academic year 2025',
        ]);
    }
}
