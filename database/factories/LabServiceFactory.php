<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabService>
 */
class LabServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $labTests = [
            ['name' => 'Complete Blood Count (CBC)', 'category' => 'Hematology', 'sample_type' => 'Blood', 'turnaround_time' => '2-4 hours', 'price' => 150.00],
            ['name' => 'Basic Metabolic Panel', 'category' => 'Chemistry', 'sample_type' => 'Blood', 'turnaround_time' => '1-2 hours', 'price' => 120.00],
            ['name' => 'Lipid Panel', 'category' => 'Chemistry', 'sample_type' => 'Blood', 'turnaround_time' => '2-3 hours', 'price' => 180.00],
            ['name' => 'Liver Function Test', 'category' => 'Chemistry', 'sample_type' => 'Blood', 'turnaround_time' => '3-4 hours', 'price' => 200.00],
            ['name' => 'Thyroid Function Test', 'category' => 'Endocrinology', 'sample_type' => 'Blood', 'turnaround_time' => '4-6 hours', 'price' => 250.00],
            ['name' => 'Urinalysis', 'category' => 'Urinalysis', 'sample_type' => 'Urine', 'turnaround_time' => '1 hour', 'price' => 75.00],
            ['name' => 'Chest X-Ray', 'category' => 'Radiology', 'sample_type' => 'None', 'turnaround_time' => '30 minutes', 'price' => 300.00],
            ['name' => 'ECG', 'category' => 'Cardiology', 'sample_type' => 'None', 'turnaround_time' => '15 minutes', 'price' => 100.00],
        ];

        $test = $this->faker->randomElement($labTests);

        return [
            'name' => $test['name'],
            'code' => strtoupper($this->faker->lexify('???')).$this->faker->numberBetween(100, 999),
            'category' => $test['category'],
            'description' => $this->faker->sentence(),
            'price' => $test['price'],
            'sample_type' => $test['sample_type'],
            'turnaround_time' => $test['turnaround_time'],
            'is_active' => $this->faker->boolean(95),
            'is_imaging' => false,
            'modality' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function imaging(?string $modality = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_imaging' => true,
            'modality' => $modality ?? $this->faker->randomElement(['X-Ray', 'CT', 'MRI', 'Ultrasound', 'Mammography']),
            'category' => 'Imaging',
            'sample_type' => 'None',
        ]);
    }

    public function laboratory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_imaging' => false,
            'modality' => null,
        ]);
    }
}
