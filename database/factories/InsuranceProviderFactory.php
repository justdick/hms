<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsuranceProvider>
 */
class InsuranceProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providers = ['VET Insurance', 'NHIS', 'AAR Health Insurance', 'Jubilee Life Insurance', 'Glico Healthcare'];
        $name = fake()->randomElement($providers);

        return [
            'name' => $name,
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'claim_submission_method' => fake()->randomElement(['online', 'manual', 'api']),
            'payment_terms_days' => fake()->randomElement([30, 45, 60, 90]),
            'is_active' => fake()->boolean(90),
            'is_nhis' => false,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the provider is an NHIS provider.
     */
    public function nhis(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'National Health Insurance Scheme',
            'is_nhis' => true,
        ]);
    }
}
