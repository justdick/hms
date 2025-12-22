<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\LabService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabOrder>
 */
class LabOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => Consultation::factory(),
            'lab_service_id' => LabService::factory(),
            'ordered_by' => User::factory(),
            'ordered_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'status' => $this->faker->randomElement(['ordered', 'sample_collected', 'in_progress', 'completed', 'cancelled']),
            'is_unpriced' => false,
            'priority' => $this->faker->randomElement(['routine', 'urgent', 'stat']),
            'special_instructions' => $this->faker->optional(0.3)->sentence(),
            'sample_collected_at' => null,
            'result_entered_at' => null,
            'result_values' => null,
            'result_notes' => null,
        ];
    }

    /**
     * Indicate that the lab order is ordered.
     */
    public function ordered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ordered',
            'sample_collected_at' => null,
            'result_entered_at' => null,
            'result_values' => null,
            'result_notes' => null,
        ]);
    }

    /**
     * Indicate that the sample has been collected.
     */
    public function sampleCollected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sample_collected',
            'sample_collected_at' => $this->faker->dateTimeBetween($attributes['ordered_at'] ?? '-1 day', 'now'),
            'result_entered_at' => null,
            'result_values' => null,
            'result_notes' => null,
        ]);
    }

    /**
     * Indicate that the test is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'sample_collected_at' => $this->faker->dateTimeBetween($attributes['ordered_at'] ?? '-1 day', 'now'),
            'result_entered_at' => null,
            'result_values' => null,
            'result_notes' => null,
        ]);
    }

    /**
     * Indicate that the test is completed.
     */
    public function completed(): static
    {
        $sampleCollectedAt = $this->faker->dateTimeBetween('-2 days', '-1 day');
        $resultEnteredAt = $this->faker->dateTimeBetween($sampleCollectedAt, 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'sample_collected_at' => $sampleCollectedAt,
            'result_entered_at' => $resultEnteredAt,
            'result_values' => [
                'hemoglobin' => $this->faker->randomFloat(1, 10, 18).' g/dL',
                'white_blood_cells' => $this->faker->numberBetween(4000, 11000).' /μL',
                'platelets' => $this->faker->numberBetween(150000, 450000).' /μL',
            ],
            'result_notes' => $this->faker->optional(0.7)->sentence(),
        ]);
    }

    /**
     * Indicate that the test is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'sample_collected_at' => null,
            'result_entered_at' => null,
            'result_values' => null,
            'result_notes' => 'Cancelled: '.$this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the test is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }

    /**
     * Indicate that the test is STAT.
     */
    public function stat(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'stat',
        ]);
    }

    /**
     * Indicate that the test is an external referral (unpriced).
     */
    public function externalReferral(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'external_referral',
            'is_unpriced' => true,
            'sample_collected_at' => null,
            'result_entered_at' => null,
            'result_values' => null,
            'result_notes' => 'External referral - unpriced service',
        ]);
    }

    /**
     * Indicate that the test is unpriced.
     */
    public function unpriced(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unpriced' => true,
        ]);
    }
}
