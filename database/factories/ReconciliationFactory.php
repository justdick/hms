<?php

namespace Database\Factories;

use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reconciliation>
 */
class ReconciliationFactory extends Factory
{
    protected $model = Reconciliation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $systemTotal = fake()->randomFloat(2, 100, 10000);
        $variance = fake()->randomFloat(2, -100, 100);
        $physicalCount = $systemTotal + $variance;

        return [
            'cashier_id' => User::factory(),
            'finance_officer_id' => User::factory(),
            'reconciliation_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'system_total' => $systemTotal,
            'physical_count' => $physicalCount,
            'variance' => $variance,
            'variance_reason' => abs($variance) > 0.01 ? fake()->sentence() : null,
            'denomination_breakdown' => null,
            'status' => abs($variance) < 0.01 ? 'balanced' : 'variance',
        ];
    }

    public function balanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'physical_count' => $attributes['system_total'],
            'variance' => 0,
            'variance_reason' => null,
            'status' => 'balanced',
        ]);
    }

    public function withVariance(float $amount = 50.00): static
    {
        return $this->state(fn (array $attributes) => [
            'physical_count' => $attributes['system_total'] + $amount,
            'variance' => $amount,
            'variance_reason' => fake()->sentence(),
            'status' => 'variance',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
