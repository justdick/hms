<?php

/**
 * Property-Based Test for Approval State Transition
 *
 * **Feature: nhis-claims-integration, Property 22: Approval State Transition**
 * **Validates: Requirements 13.3, 13.4, 13.5**
 *
 * Property: For any successfully approved claim, the status should be "vetted",
 * vetted_by should be the current user's ID, vetted_at should be the current timestamp,
 * and NHIS prices should be stored on claim items.
 */

use App\Models\Drug;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use App\Services\ClaimVettingService;
use Carbon\Carbon;

beforeEach(function () {
    InsuranceClaimItem::query()->delete();
    InsuranceClaim::query()->delete();
    NhisItemMapping::query()->delete();
    NhisTariff::query()->delete();
});

it('sets status to vetted after successful approval', function () {
    // Arrange
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $user = User::factory()->create();

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert
    expect($vettedClaim->status)->toBe('vetted');
});

it('records vetted_by as the approving user ID', function () {
    // Arrange
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $user = User::factory()->create();

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert
    expect($vettedClaim->vetted_by)->toBe($user->id);
});

it('records vetted_at as the current timestamp', function () {
    // Arrange
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'vetted_at' => null,
    ]);

    $user = User::factory()->create();

    // Freeze time for accurate comparison
    Carbon::setTestNow(now());

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert
    expect($vettedClaim->vetted_at)->not->toBeNull()
        ->and($vettedClaim->vetted_at->timestamp)->toBe(now()->timestamp);

    Carbon::setTestNow(); // Reset time
});

it('stores NHIS prices on claim items for NHIS claims', function () {
    // Arrange
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    // Create drug and NHIS tariff
    $drug = Drug::factory()->create();
    $nhisTariff = NhisTariff::factory()->medicine()->create(['price' => 50.00]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    // Create claim item without NHIS price (will be populated during vetting)
    $claimItem = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'drug',
        'code' => $drug->drug_code,
        'quantity' => 1,
        'nhis_tariff_id' => null,
        'nhis_code' => null,
        'nhis_price' => null,
    ]);

    $user = User::factory()->create();

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert: Reload the claim item to check NHIS prices
    $claimItem->refresh();

    expect($claimItem->nhis_tariff_id)->toBe($nhisTariff->id)
        ->and($claimItem->nhis_code)->toBe($nhisTariff->nhis_code)
        ->and((float) $claimItem->nhis_price)->toBe(50.00);
});

/**
 * Generate random users for property testing
 */
dataset('random_users', function () {
    return [
        fn () => User::factory()->create(['name' => 'User 1']),
        fn () => User::factory()->create(['name' => 'User 2']),
        fn () => User::factory()->create(['name' => 'User 3']),
    ];
});

it('correctly associates the approving user regardless of who they are', function (callable $userFactory) {
    // Arrange
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $user = $userFactory();

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert
    expect($vettedClaim->vetted_by)->toBe($user->id)
        ->and($vettedClaim->status)->toBe('vetted')
        ->and($vettedClaim->vetted_at)->not->toBeNull();
})->with('random_users');

it('updates total_claim_amount after approval', function () {
    // Arrange
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 150.00]);

    // Create NHIS tariff for item
    $nhisTariff = NhisTariff::factory()->lab()->create(['price' => 75.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'total_claim_amount' => 0,
    ]);

    // Create claim item with NHIS price
    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'lab',
        'quantity' => 2,
        'nhis_tariff_id' => $nhisTariff->id,
        'nhis_code' => $nhisTariff->nhis_code,
        'nhis_price' => $nhisTariff->price,
    ]);

    $user = User::factory()->create();

    // Act
    $service = app(ClaimVettingService::class);
    $vettedClaim = $service->vetClaim($claim, $user, $gdrgTariff->id);

    // Assert: Total = G-DRG (150) + Lab (75 * 2 = 150) = 300
    expect((float) $vettedClaim->total_claim_amount)->toBe(300.00);
});
