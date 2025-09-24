<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillingService>
 */
class BillingServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            // Consultation Services
            ['type' => 'consultation', 'name' => 'General Consultation', 'price' => 100.00],
            ['type' => 'consultation', 'name' => 'Specialist Consultation', 'price' => 150.00],
            ['type' => 'consultation', 'name' => 'Follow-up Consultation', 'price' => 75.00],

            // Procedures
            ['type' => 'procedure', 'name' => 'Minor Surgery', 'price' => 500.00],
            ['type' => 'procedure', 'name' => 'Wound Dressing', 'price' => 50.00],
            ['type' => 'procedure', 'name' => 'Injection Administration', 'price' => 25.00],

            // Lab Tests (basic pricing)
            ['type' => 'lab_test', 'name' => 'Blood Test', 'price' => 150.00],
            ['type' => 'lab_test', 'name' => 'Urine Test', 'price' => 75.00],
            ['type' => 'lab_test', 'name' => 'X-Ray', 'price' => 300.00],
        ];

        $service = $this->faker->randomElement($services);

        return [
            'service_type' => $service['type'],
            'service_code' => strtoupper($service['type']).'_'.$this->faker->numberBetween(100, 999),
            'service_name' => $service['name'],
            'base_price' => $service['price'],
            'is_active' => $this->faker->boolean(95),
        ];
    }

    public function consultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'consultation',
            'service_code' => 'CONSULT_'.$this->faker->numberBetween(100, 999),
        ]);
    }

    public function labTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'lab_test',
            'service_code' => 'LAB_'.$this->faker->numberBetween(100, 999),
        ]);
    }

    public function procedure(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'procedure',
            'service_code' => 'PROC_'.$this->faker->numberBetween(100, 999),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
