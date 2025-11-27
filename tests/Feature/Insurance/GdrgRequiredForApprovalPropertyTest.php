<?php

/**
 * Property-Based Test for G-DRG Required for NHIS Approval
 *
 * **Feature: nhis-claims-integration, Property 19: G-DRG Required for NHIS Approval**
 * **Validates: Requirements 9.5, 13.2**
 *
 * Property: For any NHIS claim approval attempt, if no G-DRG is selected,
 * the system should reject the approval and display an error message.
 */

use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use App\Services\ClaimVettingService;

beforeEach(function () {
    InsuranceClaim::query()->delete();
});

it('rejects NHIS claim approval without G-DRG selection', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
        'membership_id' => 'NHIS-'.fake()->randomNumber(8),
    ]);

    // Create claim WITHOUT G-DRG
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'gdrg_tariff_id' => null,
        'gdrg_amount' => null,
        'status' => 'pending_vetting',
    ]);

    $user = User::factory()->create();

    // Act & Assert: Attempting to vet without G-DRG should throw exception
    $service = app(ClaimVettingService::class);

    expect(fn () => $service->vetClaim($claim, $user, null))
        ->toThrow(InvalidArgumentException::class, 'G-DRG selection is required for NHIS claims.');
});

it('accepts NHIS claim approval with G-DRG selection', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
        'membership_id' => 'NHIS-'.fake()->randomNumber(8),
    ]);

    // Create G-DRG tariff
    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => 200.00,
    ]);

    // Create claim
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'gdrg_tariff_id' => null,
        'gdrg_amount' => null,
        'status' => 'pending_vetting',
    ]);

    $user = User::factory()->create();

    // Act: Vet claim with G-DRG
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert: Claim should be vetted successfully
    expect($vettedClaim->status)->toBe('vetted')
        ->and($vettedClaim->gdrg_tariff_id)->toBe($gdrgTariff->id)
        ->and($vettedClaim->gdrg_amount)->toBe('200.00')
        ->and($vettedClaim->vetted_by)->toBe($user->id)
        ->and($vettedClaim->vetted_at)->not->toBeNull();
});

it('allows non-NHIS claim approval without G-DRG', function () {
    // Arrange: Create non-NHIS provider and plan
    $provider = InsuranceProvider::factory()->create(['is_nhis' => false]);
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create claim WITHOUT G-DRG
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'gdrg_tariff_id' => null,
        'gdrg_amount' => null,
        'status' => 'pending_vetting',
    ]);

    $user = User::factory()->create();

    // Act: Vet claim without G-DRG (should succeed for non-NHIS)
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, null);

    // Assert: Claim should be vetted successfully
    expect($vettedClaim->status)->toBe('vetted')
        ->and($vettedClaim->gdrg_tariff_id)->toBeNull()
        ->and($vettedClaim->vetted_by)->toBe($user->id);
});

/**
 * Generate random G-DRG tariffs for property testing
 */
dataset('random_gdrg_tariffs', function () {
    $tariffs = [];
    for ($i = 0; $i < 5; $i++) {
        $tariffs[] = [fake()->randomFloat(2, 100, 1000)];
    }

    return $tariffs;
});

it('correctly associates G-DRG tariff and amount on approval', function (float $tariffPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
        'membership_id' => 'NHIS-'.fake()->randomNumber(8),
    ]);

    // Create G-DRG tariff with specific price
    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => $tariffPrice,
    ]);

    // Create claim
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $user = User::factory()->create();

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert: G-DRG tariff ID and amount should be correctly stored
    expect($vettedClaim->gdrg_tariff_id)->toBe($gdrgTariff->id)
        ->and((float) $vettedClaim->gdrg_amount)->toBe(round($tariffPrice, 2));
})->with('random_gdrg_tariffs');
