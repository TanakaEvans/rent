<?php

namespace Database\Factories\Core;

use App\Models\Core\Transformer;
use App\Models\CustomerManagement\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Core\Transformer>
 */
class TransformerFactory extends Factory
{
    protected $model = Transformer::class;

    public function definition(): array
    {
        return [
            'cm_customer_id' => Customer::factory(),
            'serial_number' => fake()->unique()->bothify('TR-####-??'),
            'make' => fake()->optional()->company(),
            'power' => fake()->optional()->randomElement(['25', '50', '100', '200', '315', '500', '1000']),
            'cooling' => fake()->optional()->randomElement(['ONAN', 'ONAF', 'OFAF']),
            'vector' => fake()->optional()->randomElement(['Dyn11', 'Yyn0', 'Dyn5']),
            'standard' => fake()->optional()->randomElement(['IEC 60076', 'BS 171', 'SANS 780']),
            'voltage_ratio' => fake()->optional()->randomElement(['11/0.4 kV', '33/11 kV', '132/33 kV']),
            'current_ratio' => fake()->optional()->randomElement(['400/5 A', '600/5 A', '800/5 A']),
            'needs_full_detail' => false,
            'created_by' => null,
        ];
    }

    public function needsFullDetail(): static
    {
        return $this->state(fn (array $attributes) => [
            'needs_full_detail' => true,
        ]);
    }
}