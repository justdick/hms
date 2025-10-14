<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dispensing>
 */
class DispensingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitPrice = fake()->randomFloat(2, 10, 500);

        return [
            'prescription_id' => \App\Models\Prescription::factory(),
            'drug_batch_id' => \App\Models\DrugBatch::factory(),
            'dispensed_by' => \App\Models\User::factory(),
            'quantity_dispensed' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $quantity * $unitPrice,
            'instructions' => fake()->sentence(),
            'dispensed_at' => now(),
            'status' => 'dispensed',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
