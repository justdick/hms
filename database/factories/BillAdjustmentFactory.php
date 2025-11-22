<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillAdjustment>
 */
class BillAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalAmount = fake()->randomFloat(2, 50, 500);
        $adjustmentType = fake()->randomElement(['waiver', 'discount_percentage', 'discount_fixed']);

        $adjustmentAmount = match ($adjustmentType) {
            'waiver' => $originalAmount,
            'discount_percentage' => $originalAmount * (fake()->numberBetween(10, 50) / 100),
            'discount_fixed' => fake()->randomFloat(2, 10, $originalAmount * 0.5),
        };

        $finalAmount = $originalAmount - $adjustmentAmount;

        return [
            'charge_id' => \App\Models\Charge::factory(),
            'adjustment_type' => $adjustmentType,
            'original_amount' => $originalAmount,
            'adjustment_amount' => $adjustmentAmount,
            'final_amount' => $finalAmount,
            'reason' => fake()->sentence(15),
            'adjusted_by' => \App\Models\User::factory(),
            'adjusted_at' => now(),
        ];
    }
}
