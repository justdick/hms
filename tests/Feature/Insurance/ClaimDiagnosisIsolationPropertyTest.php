<?php

/**
 * Property-Based Test for Claim Diagnosis Isolation
 *
 * **Feature: nhis-claims-integration, Property 20: Claim Diagnosis Isolation**
 * **Validates: Requirements 10.2, 10.3**
 *
 * Property: For any diagnosis added to or removed from a claim,
 * the original consultation's diagnoses should remain unchanged.
 */

use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Diagnosis;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    InsuranceClaimDiagnosis::query()->delete();
    InsuranceClaim::query()->delete();
    ConsultationDiagnosis::query()->delete();
    Consultation::query()->delete();

    Permission::firstOrCreate(['name' => 'insurance.vet-claims']);
    Permission::firstOrCreate(['name' => 'system.admin']);
});

it('does not modify consultation diagnoses when adding diagnoses to claim', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create consultation with diagnoses
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    // Create original consultation diagnoses
    $originalDiagnoses = Diagnosis::factory()->count(2)->create();
    foreach ($originalDiagnoses as $index => $diagnosis) {
        ConsultationDiagnosis::create([
            'consultation_id' => $consultation->id,
            'diagnosis_id' => $diagnosis->id,
            'type' => $index === 0 ? 'principal' : 'provisional',
        ]);
    }

    // Store original consultation diagnosis IDs for comparison
    $originalConsultationDiagnosisIds = $consultation->diagnoses()->pluck('diagnosis_id')->sort()->values()->toArray();

    // Create claim linked to consultation
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'consultation_id' => $consultation->id,
        'status' => 'pending_vetting',
    ]);

    // Create new diagnoses to add to claim
    $newDiagnoses = Diagnosis::factory()->count(3)->create();

    // Act: Add diagnoses to claim via controller
    $response = $this->actingAs($user)
        ->postJson("/admin/insurance/claims/{$claim->id}/diagnoses", [
            'diagnoses' => $newDiagnoses->map(fn ($d, $i) => [
                'diagnosis_id' => $d->id,
                'is_primary' => $i === 0,
            ])->toArray(),
        ]);

    $response->assertOk();

    // Assert: Consultation diagnoses remain unchanged
    $consultation->refresh();
    $currentConsultationDiagnosisIds = $consultation->diagnoses()->pluck('diagnosis_id')->sort()->values()->toArray();

    expect($currentConsultationDiagnosisIds)->toBe($originalConsultationDiagnosisIds)
        ->and($consultation->diagnoses()->count())->toBe(2);
});

it('does not modify consultation diagnoses when removing diagnoses from claim', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create consultation with diagnoses
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    // Create original consultation diagnoses
    $originalDiagnoses = Diagnosis::factory()->count(3)->create();
    foreach ($originalDiagnoses as $index => $diagnosis) {
        ConsultationDiagnosis::create([
            'consultation_id' => $consultation->id,
            'diagnosis_id' => $diagnosis->id,
            'type' => $index === 0 ? 'principal' : 'provisional',
        ]);
    }

    // Store original consultation diagnosis IDs
    $originalConsultationDiagnosisIds = $consultation->diagnoses()->pluck('diagnosis_id')->sort()->values()->toArray();

    // Create claim with its own diagnoses
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'consultation_id' => $consultation->id,
        'status' => 'pending_vetting',
    ]);

    // Add diagnoses to claim
    foreach ($originalDiagnoses as $index => $diagnosis) {
        InsuranceClaimDiagnosis::create([
            'insurance_claim_id' => $claim->id,
            'diagnosis_id' => $diagnosis->id,
            'is_primary' => $index === 0,
        ]);
    }

    // Act: Update claim to have only 1 diagnosis (removing 2)
    $response = $this->actingAs($user)
        ->postJson("/admin/insurance/claims/{$claim->id}/diagnoses", [
            'diagnoses' => [
                [
                    'diagnosis_id' => $originalDiagnoses->first()->id,
                    'is_primary' => true,
                ],
            ],
        ]);

    $response->assertOk();

    // Assert: Consultation diagnoses remain unchanged
    $consultation->refresh();
    $currentConsultationDiagnosisIds = $consultation->diagnoses()->pluck('diagnosis_id')->sort()->values()->toArray();

    expect($currentConsultationDiagnosisIds)->toBe($originalConsultationDiagnosisIds)
        ->and($consultation->diagnoses()->count())->toBe(3);

    // Assert: Claim now has only 1 diagnosis
    expect($claim->claimDiagnoses()->count())->toBe(1);
});

it('allows claim to have different diagnoses than consultation', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create consultation with diagnoses
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    // Create consultation diagnoses
    $consultationDiagnoses = Diagnosis::factory()->count(2)->create();
    foreach ($consultationDiagnoses as $index => $diagnosis) {
        ConsultationDiagnosis::create([
            'consultation_id' => $consultation->id,
            'diagnosis_id' => $diagnosis->id,
            'type' => $index === 0 ? 'principal' : 'provisional',
        ]);
    }

    // Create claim
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'consultation_id' => $consultation->id,
        'status' => 'pending_vetting',
    ]);

    // Create completely different diagnoses for claim
    $claimDiagnoses = Diagnosis::factory()->count(3)->create();

    // Act: Set claim diagnoses to be completely different
    $response = $this->actingAs($user)
        ->postJson("/admin/insurance/claims/{$claim->id}/diagnoses", [
            'diagnoses' => $claimDiagnoses->map(fn ($d, $i) => [
                'diagnosis_id' => $d->id,
                'is_primary' => $i === 0,
            ])->toArray(),
        ]);

    $response->assertOk();

    // Assert: Claim has different diagnoses than consultation
    $claimDiagnosisIds = $claim->claimDiagnoses()->pluck('diagnosis_id')->sort()->values()->toArray();
    $consultationDiagnosisIds = $consultation->diagnoses()->pluck('diagnosis_id')->sort()->values()->toArray();

    expect($claimDiagnosisIds)->not->toBe($consultationDiagnosisIds)
        ->and(count(array_intersect($claimDiagnosisIds, $consultationDiagnosisIds)))->toBe(0);
});

/**
 * Property test with random diagnosis counts
 */
dataset('random_diagnosis_counts', function () {
    return [
        'single diagnosis' => [1],
        'two diagnoses' => [2],
        'three diagnoses' => [3],
        'five diagnoses' => [5],
    ];
});

it('maintains consultation diagnosis count regardless of claim diagnosis changes', function (int $consultationDiagnosisCount) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create consultation with specified number of diagnoses
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    $consultationDiagnoses = Diagnosis::factory()->count($consultationDiagnosisCount)->create();
    foreach ($consultationDiagnoses as $index => $diagnosis) {
        ConsultationDiagnosis::create([
            'consultation_id' => $consultation->id,
            'diagnosis_id' => $diagnosis->id,
            'type' => $index === 0 ? 'principal' : 'provisional',
        ]);
    }

    // Create claim
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'consultation_id' => $consultation->id,
        'status' => 'pending_vetting',
    ]);

    // Create different number of diagnoses for claim
    $claimDiagnoses = Diagnosis::factory()->count(rand(1, 10))->create();

    // Act: Update claim diagnoses multiple times
    for ($i = 0; $i < 3; $i++) {
        $randomDiagnoses = $claimDiagnoses->random(rand(1, $claimDiagnoses->count()));

        $this->actingAs($user)
            ->postJson("/admin/insurance/claims/{$claim->id}/diagnoses", [
                'diagnoses' => $randomDiagnoses->map(fn ($d, $idx) => [
                    'diagnosis_id' => $d->id,
                    'is_primary' => $idx === 0,
                ])->toArray(),
            ]);
    }

    // Assert: Consultation diagnosis count remains unchanged
    $consultation->refresh();
    expect($consultation->diagnoses()->count())->toBe($consultationDiagnosisCount);
})->with('random_diagnosis_counts');
