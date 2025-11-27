<?php

/**
 * Property-Based Test for NHIS Patient Pays Only Copay
 *
 * **Feature: nhis-claims-integration, Property 10: NHIS Patient Pays Only Copay**
 * **Validates: Requirements 5.3**
 *
 * Property: For any coverage calculation for an NHIS patient, the patient_pays
 * amount should equal only the copay amount from the coverage rule, with no
 * percentage-based calculation applied.
 */

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Services\InsuranceCoverageService;

beforeEach(function () {
    // Clean up existing data
    NhisItemMapping::query()->delete();
    NhisTariff::query()->delete();
    InsuranceCoverageRule::query()->delete();
});

/**
 * Generate random copay amounts for property testing
 */
dataset('random_copay_amounts', function () {
    $amounts = [];
    for ($i = 0; $i < 15; $i++) {
        $amounts[] = [fake()->randomFloat(2, 0, 50)];
    }

    return $amounts;
});

/**
 * Generate random quantities for property testing
 */
dataset('random_quantities', function () {
    return [
        [1],
        [2],
        [3],
        [5],
        [10],
    ];
});

it('patient pays only the fixed copay amount for NHIS coverage', function (float $copayAmount) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 200.00,
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 150.00,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a coverage rule with copay amount
    // Note: For NHIS, percentage-based copay should be ignored
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'percentage', // This should be ignored for NHIS
        'coverage_value' => 80, // 80% coverage - should be ignored for NHIS
        'patient_copay_percentage' => 20, // Should be ignored for NHIS
        'patient_copay_amount' => $copayAmount, // Only this should be used
    ]);

    // Act: Calculate coverage
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: patient_pays should equal only the copay amount
    expect($result['is_covered'])->toBeTrue()
        ->and($result['patient_pays'])->toBe(round($copayAmount, 2))
        ->and($result['is_nhis'])->toBeTrue();
})->with('random_copay_amounts');

it('patient pays copay multiplied by quantity', function (float $copayAmount, int $quantity) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 80.00,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a coverage rule with copay amount
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'full',
        'patient_copay_amount' => $copayAmount,
    ]);

    // Act: Calculate coverage with quantity
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: $quantity,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: patient_pays should equal copay * quantity
    $expectedPatientPays = round($copayAmount * $quantity, 2);
    expect($result['is_covered'])->toBeTrue()
        ->and($result['patient_pays'])->toBe($expectedPatientPays);
})->with('random_copay_amounts')->with('random_quantities');

it('patient pays zero when copay is zero', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 150.00,
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 100.00,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a coverage rule with zero copay amount
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'full',
        'patient_copay_amount' => 0, // Zero copay
    ]);

    // Act: Calculate coverage
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: patient_pays should be zero
    expect($result['is_covered'])->toBeTrue()
        ->and($result['patient_pays'])->toBe(0.00)
        ->and($result['insurance_pays'])->toBe(100.00); // Full NHIS tariff price
});

it('patient pays zero when no coverage rule exists', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 150.00,
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 100.00,
    ]);

    // Create mapping but NO coverage rule
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Act: Calculate coverage (no coverage rule exists)
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: patient_pays should be zero (no copay defined)
    expect($result['is_covered'])->toBeTrue()
        ->and($result['patient_pays'])->toBe(0.00)
        ->and($result['insurance_pays'])->toBe(100.00)
        ->and($result['rule_type'])->toBe('nhis_default');
});

it('ignores percentage-based patient copay for NHIS', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 200.00,
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 100.00,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a coverage rule with ONLY percentage copay (no fixed copay)
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80, // 80% coverage
        'patient_copay_percentage' => 20, // 20% copay - should be ignored for NHIS
        'patient_copay_amount' => 0, // No fixed copay
    ]);

    // Act: Calculate coverage
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: patient_pays should be zero (percentage copay ignored)
    // For NHIS, insurance pays full NHIS tariff, patient pays only fixed copay
    expect($result['is_covered'])->toBeTrue()
        ->and($result['patient_pays'])->toBe(0.00)
        ->and($result['insurance_pays'])->toBe(100.00); // Full NHIS tariff
});

it('uses general category rule copay when no specific rule exists', function (float $copayAmount) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 150.00,
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => 100.00,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a GENERAL coverage rule (no item_code) with copay
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => null, // General rule
        'is_covered' => true,
        'coverage_type' => 'full',
        'patient_copay_amount' => $copayAmount,
    ]);

    // Act: Calculate coverage
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: patient_pays should use general rule copay
    expect($result['is_covered'])->toBeTrue()
        ->and($result['patient_pays'])->toBe(round($copayAmount, 2))
        ->and($result['rule_type'])->toBe('general');
})->with('random_copay_amounts');
