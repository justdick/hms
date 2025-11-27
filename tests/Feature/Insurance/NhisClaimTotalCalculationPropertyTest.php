<?php

/**
 * Property-Based Test for NHIS Claim Total Calculation
 *
 * **Feature: nhis-claims-integration, Property 18: NHIS Claim Total Calculation**
 * **Validates: Requirements 12.1, 9.4**
 *
 * Property: For any NHIS claim with a selected G-DRG, the grand total should equal:
 * G-DRG tariff price + sum of mapped investigation prices + sum of mapped prescription prices
 * + sum of mapped procedure prices.
 */

use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Services\ClaimVettingService;

beforeEach(function () {
    // Clean up existing data
    InsuranceClaimItem::query()->delete();
    InsuranceClaim::query()->delete();
});

/**
 * Generate random G-DRG prices for property testing
 */
dataset('random_gdrg_prices', function () {
    $prices = [];
    for ($i = 0; $i < 10; $i++) {
        $prices[] = [fake()->randomFloat(2, 50, 500)];
    }

    return $prices;
});

/**
 * Generate random item counts for property testing
 */
dataset('random_item_counts', function () {
    return [
        [1, 1, 1],
        [2, 3, 1],
        [0, 2, 2],
        [3, 0, 1],
        [1, 2, 0],
        [0, 0, 0],
    ];
});

it('calculates grand total as G-DRG + investigations + prescriptions + procedures', function (float $gdrgPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    // Create G-DRG tariff
    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => $gdrgPrice,
    ]);

    // Create NHIS tariffs for items
    $labTariff = NhisTariff::factory()->lab()->create(['price' => 25.00]);
    $drugTariff = NhisTariff::factory()->medicine()->create(['price' => 15.00]);
    $procedureTariff = NhisTariff::factory()->procedure()->create(['price' => 100.00]);

    // Create claim with G-DRG
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'gdrg_tariff_id' => $gdrgTariff->id,
        'gdrg_amount' => $gdrgPrice,
    ]);

    // Create claim items with NHIS prices
    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'lab',
        'quantity' => 2,
        'nhis_tariff_id' => $labTariff->id,
        'nhis_code' => $labTariff->nhis_code,
        'nhis_price' => $labTariff->price,
    ]);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'drug',
        'quantity' => 3,
        'nhis_tariff_id' => $drugTariff->id,
        'nhis_code' => $drugTariff->nhis_code,
        'nhis_price' => $drugTariff->price,
    ]);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'procedure',
        'quantity' => 1,
        'nhis_tariff_id' => $procedureTariff->id,
        'nhis_code' => $procedureTariff->nhis_code,
        'nhis_price' => $procedureTariff->price,
    ]);

    // Act: Calculate claim total
    $service = app(ClaimVettingService::class);
    $totals = $service->calculateClaimTotal($claim);

    // Assert: Grand total = G-DRG + (lab * qty) + (drug * qty) + (procedure * qty)
    $expectedInvestigations = 25.00 * 2; // 50.00
    $expectedPrescriptions = 15.00 * 3;  // 45.00
    $expectedProcedures = 100.00 * 1;    // 100.00
    $expectedGrandTotal = $gdrgPrice + $expectedInvestigations + $expectedPrescriptions + $expectedProcedures;

    expect($totals['gdrg'])->toBe(round($gdrgPrice, 2))
        ->and($totals['investigations'])->toBe(round($expectedInvestigations, 2))
        ->and($totals['prescriptions'])->toBe(round($expectedPrescriptions, 2))
        ->and($totals['procedures'])->toBe(round($expectedProcedures, 2))
        ->and($totals['grand_total'])->toBe(round($expectedGrandTotal, 2));
})->with('random_gdrg_prices');

it('excludes unmapped items from NHIS claim total', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    // Create G-DRG tariff
    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => 200.00,
    ]);

    // Create NHIS tariff for mapped item
    $mappedTariff = NhisTariff::factory()->lab()->create(['price' => 50.00]);

    // Create claim
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'gdrg_tariff_id' => $gdrgTariff->id,
        'gdrg_amount' => 200.00,
    ]);

    // Create mapped item (has NHIS price)
    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'lab',
        'quantity' => 1,
        'subtotal' => 50.00,
        'nhis_tariff_id' => $mappedTariff->id,
        'nhis_code' => $mappedTariff->nhis_code,
        'nhis_price' => $mappedTariff->price,
    ]);

    // Create unmapped item (no NHIS price)
    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'item_type' => 'drug',
        'quantity' => 1,
        'subtotal' => 100.00,
        'nhis_tariff_id' => null,
        'nhis_code' => null,
        'nhis_price' => null,
    ]);

    // Act: Calculate claim total
    $service = app(ClaimVettingService::class);
    $totals = $service->calculateClaimTotal($claim);

    // Assert: Unmapped item should be excluded from total
    // Grand total = G-DRG (200) + mapped lab (50) = 250
    // NOT including unmapped drug (100)
    expect($totals['grand_total'])->toBe(250.00)
        ->and($totals['investigations'])->toBe(50.00)
        ->and($totals['prescriptions'])->toBe(0.00) // Unmapped drug excluded
        ->and($totals['unmapped_count'])->toBe(1);
});

it('handles claims with varying item counts correctly', function (int $labCount, int $drugCount, int $procedureCount) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    $patient = Patient::factory()->create();

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgPrice = 150.00;
    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => $gdrgPrice]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'gdrg_tariff_id' => $gdrgTariff->id,
        'gdrg_amount' => $gdrgPrice,
    ]);

    $labPrice = 30.00;
    $drugPrice = 20.00;
    $procedurePrice = 80.00;

    // Create lab items
    for ($i = 0; $i < $labCount; $i++) {
        $tariff = NhisTariff::factory()->lab()->create(['price' => $labPrice]);
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'lab',
            'quantity' => 1,
            'nhis_tariff_id' => $tariff->id,
            'nhis_code' => $tariff->nhis_code,
            'nhis_price' => $tariff->price,
        ]);
    }

    // Create drug items
    for ($i = 0; $i < $drugCount; $i++) {
        $tariff = NhisTariff::factory()->medicine()->create(['price' => $drugPrice]);
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'quantity' => 1,
            'nhis_tariff_id' => $tariff->id,
            'nhis_code' => $tariff->nhis_code,
            'nhis_price' => $tariff->price,
        ]);
    }

    // Create procedure items
    for ($i = 0; $i < $procedureCount; $i++) {
        $tariff = NhisTariff::factory()->procedure()->create(['price' => $procedurePrice]);
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'procedure',
            'quantity' => 1,
            'nhis_tariff_id' => $tariff->id,
            'nhis_code' => $tariff->nhis_code,
            'nhis_price' => $tariff->price,
        ]);
    }

    // Act
    $service = app(ClaimVettingService::class);
    $totals = $service->calculateClaimTotal($claim);

    // Assert
    $expectedInvestigations = $labPrice * $labCount;
    $expectedPrescriptions = $drugPrice * $drugCount;
    $expectedProcedures = $procedurePrice * $procedureCount;
    $expectedGrandTotal = $gdrgPrice + $expectedInvestigations + $expectedPrescriptions + $expectedProcedures;

    expect($totals['investigations'])->toBe(round($expectedInvestigations, 2))
        ->and($totals['prescriptions'])->toBe(round($expectedPrescriptions, 2))
        ->and($totals['procedures'])->toBe(round($expectedProcedures, 2))
        ->and($totals['grand_total'])->toBe(round($expectedGrandTotal, 2));
})->with('random_item_counts');
