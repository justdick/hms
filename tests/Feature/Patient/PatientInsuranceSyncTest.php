<?php

use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('syncs insurance dates from NHIS verification', function () {
    $provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'coverage_start_date' => '2024-01-01',
        'coverage_end_date' => '2024-12-31',
    ]);

    $response = $this->actingAs($this->user)
        ->patch("/patient-insurance/{$patientInsurance->id}/sync-dates", [
            'coverage_start_date' => '2025-01-01',
            'coverage_end_date' => '2025-12-31',
        ]);

    $response->assertRedirect();

    $patientInsurance->refresh();
    expect($patientInsurance->coverage_start_date->format('Y-m-d'))->toBe('2025-01-01')
        ->and($patientInsurance->coverage_end_date->format('Y-m-d'))->toBe('2025-12-31');
});

it('returns info message when dates are already up to date', function () {
    $provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'coverage_start_date' => '2025-01-01',
        'coverage_end_date' => '2025-12-31',
    ]);

    $response = $this->actingAs($this->user)
        ->patch("/patient-insurance/{$patientInsurance->id}/sync-dates", [
            'coverage_start_date' => '2025-01-01',
            'coverage_end_date' => '2025-12-31',
        ]);

    $response->assertRedirect()
        ->assertSessionHas('info', 'Insurance dates are already up to date.');
});

it('validates required fields', function () {
    $provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patch("/patient-insurance/{$patientInsurance->id}/sync-dates", []);

    $response->assertSessionHasErrors(['coverage_start_date', 'coverage_end_date']);
});

it('validates end date is after start date', function () {
    $provider = InsuranceProvider::factory()->create(['is_nhis' => true]);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patch("/patient-insurance/{$patientInsurance->id}/sync-dates", [
            'coverage_start_date' => '2025-12-31',
            'coverage_end_date' => '2025-01-01',
        ]);

    $response->assertSessionHasErrors(['coverage_end_date']);
});
