<?php

namespace Database\Factories\Core;

use App\Enums\Core\CertificateStatus;
use App\Models\Core\Certificate;
use App\Models\Core\TestingJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Core\Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'core_testing_job_id' => TestingJob::factory(),
            'certificate_number' => fake()->unique()->bothify('T-####-###'),
            'version' => 1,
            'status' => CertificateStatus::Draft->value,
            'recommendations' => fake()->optional()->sentence(),
            'issued_by' => null,
            'issued_at' => null,
            'superseded_by_id' => null,
            'closure_reason' => null,
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::Issued->value,
            'issued_at' => now(),
        ]);
    }

    public function cancelled(string $reason = 'Cancelled for test'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificateStatus::Cancelled->value,
            'closure_reason' => $reason,
            'closed_at' => now(),
        ]);
    }
}