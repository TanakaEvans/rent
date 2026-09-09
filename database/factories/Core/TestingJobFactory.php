<?php

namespace Database\Factories\Core;

use App\Enums\Core\ApprovalTier;
use App\Enums\Core\TestingJobStatus;
use App\Models\Core\TestingJob;
use App\Models\Core\Transformer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Core\TestingJob>
 */
class TestingJobFactory extends Factory
{
    protected $model = TestingJob::class;

    public function definition(): array
    {
        return [
            'core_transformer_id' => Transformer::factory(),
            'work_order' => fake()->unique()->bothify('WO-####-??'),
            'test_date' => fake()->date(),
            'temperature' => fake()->optional()->numberBetween(10, 40),
            'status' => TestingJobStatus::Draft->value,
            'approval_tier_required' => ApprovalTier::Single->value,
            'submitted_by' => null,
            'submitted_at' => null,
            'created_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestingJobStatus::UnderReview->value,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TestingJobStatus::Approved->value,
            'submitted_at' => now()->subDay(),
        ]);
    }
}