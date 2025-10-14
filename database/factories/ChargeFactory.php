<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Charge>
 */
class ChargeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 5000);

        return [
            'patient_checkin_id' => \App\Models\PatientCheckin::factory(),
            'prescription_id' => null,
            'service_type' => fake()->randomElement(['consultation', 'lab', 'pharmacy', 'ward']),
            'service_code' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
            'description' => fake()->sentence(),
            'amount' => $amount,
            'charge_type' => fake()->randomElement(['consultation', 'procedure', 'medication', 'test', 'room']),
            'status' => 'pending',
            'paid_amount' => 0,
            'charged_at' => now(),
            'created_by_type' => \App\Models\User::class,
            'created_by_id' => \App\Models\User::factory(),
            'is_emergency_override' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_amount' => 0,
            'paid_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_amount' => $attributes['amount'],
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'amount' => 0,
        ]);
    }

    public function pharmacy(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'pharmacy',
            'charge_type' => 'medication',
            'prescription_id' => \App\Models\Prescription::factory(),
        ]);
    }
}
