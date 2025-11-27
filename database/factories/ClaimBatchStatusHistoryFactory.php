<?php

namespace Database\Factories;

use App\Models\ClaimBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClaimBatchStatusHistory>
 */
class ClaimBatchStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['draft', 'finalized', 'submitted', 'processing', 'completed'];
        $previousIndex = fake()->numberBetween(0, count($statuses) - 2);

        return [
            'claim_batch_id' => ClaimBatch::factory(),
            'user_id' => User::factory(),
            'previous_status' => $statuses[$previousIndex],
            'new_status' => $statuses[$previousIndex + 1],
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate this is the initial creation entry.
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_status' => null,
            'new_status' => 'draft',
            'notes' => 'Batch created',
        ]);
    }
}
