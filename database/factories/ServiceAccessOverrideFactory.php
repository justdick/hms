<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceAccessOverride>
 */
class ServiceAccessOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_checkin_id' => \App\Models\PatientCheckin::factory(),
            'service_type' => fake()->randomElement(['consultation', 'laboratory', 'pharmacy', 'ward']),
            'service_code' => fake()->optional()->regexify('[A-Z]{3}[0-9]{3}'),
            'reason' => fake()->sentence(20),
            'authorized_by' => \App\Models\User::factory(),
            'authorized_at' => now(),
            'expires_at' => now()->addHours(2),
            'is_active' => true,
        ];
    }
}
