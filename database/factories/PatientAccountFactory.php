<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientAccount>
 */
class PatientAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'balance' => 0,
            'credit_limit' => 0,
            'is_active' => true,
        ];
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    public function withCreditLimit(float $limit, ?User $authorizedBy = null): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => $limit,
            'credit_authorized_by' => $authorizedBy?->id ?? User::factory(),
            'credit_authorized_at' => now(),
            'credit_reason' => fake()->sentence(),
        ]);
    }

    public function inDebt(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => -abs($amount),
        ]);
    }
}
