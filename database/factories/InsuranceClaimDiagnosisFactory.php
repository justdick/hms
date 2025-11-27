<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\InsuranceClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsuranceClaimDiagnosis>
 */
class InsuranceClaimDiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'insurance_claim_id' => InsuranceClaim::factory(),
            'diagnosis_id' => Diagnosis::factory(),
            'is_primary' => false,
        ];
    }

    /**
     * Indicate that the diagnosis is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that the diagnosis is secondary.
     */
    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
        ]);
    }
}
