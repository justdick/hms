<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimBatch>
 */
class ClaimBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submissionPeriod = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'batch_number' => 'BATCH-'.fake()->unique()->numerify('######'),
            'name' => fake()->words(3, true).' Batch',
            'submission_period' => $submissionPeriod,
            'status' => 'draft',
            'total_claims' => 0,
            'total_amount' => 0,
            'approved_amount' => null,
            'paid_amount' => null,
            'submitted_at' => null,
            'exported_at' => null,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the batch is finalized.
     */
    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finalized',
        ]);
    }

    /**
     * Indicate that the batch is submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Indicate that the batch is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'submitted_at' => now()->subDays(30),
            'paid_at' => now(),
        ]);
    }

    /**
     * Set the batch with claims.
     */
    public function withClaims(int $count, float $totalAmount): static
    {
        return $this->state(fn (array $attributes) => [
            'total_claims' => $count,
            'total_amount' => $totalAmount,
        ]);
    }
}
