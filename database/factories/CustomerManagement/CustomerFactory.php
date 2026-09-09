<?php

namespace Database\Factories\CustomerManagement;

use App\Enums\CustomerManagement\CustomerStatus;
use App\Models\CustomerManagement\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerManagement\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'contact_email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'customer_reference' => fake()->boolean(60)
                ? 'REF-'.fake()->unique()->numberBetween(10000, 99999)
                : null,
            'status' => CustomerStatus::Active->value,
            'needs_full_detail' => false,
            'created_by' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::Archived->value,
        ]);
    }

    public function needsFullDetail(): static
    {
        return $this->state(fn (array $attributes) => [
            'needs_full_detail' => true,
        ]);
    }
}
