<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsuranceClaim>
 */
class InsuranceClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $patient = \App\Models\Patient::factory()->create();
        $attendanceDate = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'claim_check_code' => fake()->unique()->numerify('#####'),
            'folder_id' => fake()->optional()->regexify('[A-Z]{2}[0-9]{4}'),
            'patient_id' => $patient->id,
            'patient_insurance_id' => \App\Models\PatientInsurance::factory(),
            'patient_checkin_id' => null,
            'consultation_id' => null,
            'patient_admission_id' => null,
            'patient_surname' => $patient->last_name,
            'patient_other_names' => $patient->first_name,
            'patient_dob' => $patient->date_of_birth,
            'patient_gender' => $patient->gender,
            'membership_id' => fake()->numerify('########'),
            'date_of_attendance' => $attendanceDate,
            'date_of_discharge' => fake()->optional()->dateTimeBetween($attendanceDate, 'now'),
            'type_of_service' => fake()->randomElement(['OPD', 'IPD']),
            'type_of_attendance' => fake()->randomElement(['EAE', 'ANC', 'PNC', 'FP', 'CWC', 'REV']),
            'specialty_attended' => fake()->optional()->randomElement(['General Medicine', 'Surgery', 'Pediatrics', 'OB/GYN']),
            'attending_prescriber' => fake()->optional()->name(),
            'is_unbundled' => fake()->boolean(10),
            'is_pharmacy_included' => fake()->boolean(90),
            'primary_diagnosis_code' => fake()->optional()->regexify('[A-Z][0-9]{2}\.[0-9]'),
            'primary_diagnosis_description' => fake()->optional()->sentence(4),
            'secondary_diagnoses' => null,
            'c_drg_code' => fake()->optional()->regexify('[A-Z0-9]{5}'),
            'gdrg_tariff_id' => null,
            'gdrg_amount' => null,
            'hin_number' => fake()->optional()->numerify('##########'),
            'total_claim_amount' => fake()->randomFloat(2, 100, 5000),
            'approved_amount' => 0,
            'patient_copay_amount' => 0,
            'insurance_covered_amount' => 0,
            'status' => fake()->randomElement(['draft', 'pending_vetting', 'vetted', 'submitted']),
            'vetted_by' => null,
            'vetted_at' => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'submission_date' => null,
            'approval_date' => null,
            'payment_date' => null,
            'rejection_reason' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
