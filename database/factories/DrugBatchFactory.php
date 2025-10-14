<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DrugBatch>
 */
class DrugBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityReceived = fake()->numberBetween(100, 1000);

        return [
            'drug_id' => \App\Models\Drug::factory(),
            'supplier_id' => \App\Models\Supplier::factory(),
            'batch_number' => fake()->unique()->regexify('BATCH-[0-9]{6}'),
            'expiry_date' => fake()->dateTimeBetween('+6 months', '+2 years'),
            'manufacture_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'quantity_received' => $quantityReceived,
            'quantity_remaining' => $quantityReceived,
            'cost_per_unit' => fake()->randomFloat(2, 5, 100),
            'selling_price_per_unit' => fake()->randomFloat(2, 10, 200),
            'received_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function expiringSoon(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => fake()->dateTimeBetween('now', "+{$days} days"),
        ]);
    }
}
