<?php

namespace Database\Factories;

use App\Models\BillingOverride;
use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingOverride>
 */
class BillingOverrideFactory extends Factory
{
    protected $model = BillingOverride::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_checkin_id' => PatientCheckin::factory(),
            'charge_id' => null,
            'authorized_by' => User::factory(),
            'service_type' => fake()->randomElement(['consultation', 'laboratory', 'pharmacy', 'ward', 'procedure']),
            'reason' => fake()->sentence(),
            'status' => BillingOverride::STATUS_ACTIVE,
            'authorized_at' => now(),
            'expires_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingOverride::STATUS_ACTIVE,
            'expires_at' => null,
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingOverride::STATUS_USED,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillingOverride::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function withCharge(): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_id' => Charge::factory(),
        ]);
    }

    public function expiresIn(int $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addHours($hours),
        ]);
    }
}
