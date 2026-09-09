<?php

namespace Database\Factories;

use App\Models\AdmissionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdmissionTypeFactory extends Factory
{
    protected $model = AdmissionType::class;

    public function definition(): array
    {
        $types = [
            ['name' => 'New Admission', 'code' => 'NEW'],
            ['name' => 'Transfer', 'code' => 'TRF'],
            ['name' => 'Re-admission', 'code' => 'REA'],
            ['name' => 'Direct Entry', 'code' => 'DIR'],
            ['name' => 'Scholarship Admission', 'code' => 'SCH'],
        ];

        $type = $this->faker->randomElement($types);

        return [
            'name' => $type['name'],
            'code' => $type['code'],
            'description' => "Admission type: {$type['name']}",
            'is_active' => true,
        ];
    }

    /**
     * Create a specific admission type
     */
    public function type(string $name, string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'code' => $code,
            'description' => "Admission type: {$name}",
            'is_active' => true,
        ]);
    }

    /**
     * Inactive admission type
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
