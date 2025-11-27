<?php

namespace Database\Factories;

use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimBatchItem>
 */
class ClaimBatchItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $claimAmount = fake()->randomFloat(2, 100, 5000);

        return [
            'claim_batch_id' => ClaimBatch::factory(),
            'insurance_claim_id' => InsuranceClaim::factory(),
            'claim_amount' => $claimAmount,
            'approved_amount' => null,
            'status' => 'pending',
            'rejection_reason' => null,
        ];
    }

    /**
     * Indicate that the item is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_amount' => $attributes['claim_amount'],
        ]);
    }

    /**
     * Indicate that the item is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_amount' => 0,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the item is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'approved_amount' => $attributes['claim_amount'],
        ]);
    }
}
