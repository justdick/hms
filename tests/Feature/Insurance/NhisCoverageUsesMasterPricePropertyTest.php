<?php

/**
 * Property-Based Test for NHIS Coverage Uses Master Price
 *
 * **Feature: nhis-claims-integration, Property 9: NHIS Coverage Uses Master Price**
 * **Validates: Requirements 4.2, 5.1, 5.2**
 *
 * Property: For any coverage calculation for an NHIS patient with a mapped item,
 * the insurance_pays amount should equal the NHIS Tariff Master price
 * (not the coverage rule tariff_amount).
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
 * Generate random NHIS tariff prices for property testing
 */
dataset('random_nhis_prices', function () {
    $prices = [];
    for ($i = 0; $i < 15; $i++) {
        $prices[] = [fake()->randomFloat(2, 10, 500)];
    }

    return $prices;
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

it('uses NHIS Master price for insurance_pays when item is mapped', function (float $nhisTariffPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug with a different hospital price
    $hospitalPrice = $nhisTariffPrice * 1.5; // Hospital price is 50% higher
    $drug = Drug::factory()->create([
        'unit_price' => $hospitalPrice,
    ]);

    // Create NHIS tariff with specific price
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $nhisTariffPrice,
    ]);

    // Create mapping between drug and NHIS tariff
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a coverage rule with a different tariff_amount (should be ignored for NHIS)
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'full',
        'tariff_amount' => $hospitalPrice * 0.8, // Different tariff amount
        'patient_copay_amount' => 0,
    ]);

    // Act: Calculate coverage
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $hospitalPrice,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: insurance_pays should equal NHIS tariff price, not hospital price or rule tariff
    expect($result['is_covered'])->toBeTrue()
        ->and($result['insurance_pays'])->toBe(round($nhisTariffPrice, 2))
        ->and($result['insurance_tariff'])->toBe($nhisTariffPrice)
        ->and($result['is_nhis'])->toBeTrue()
        ->and($result['nhis_code'])->toBe($nhisTariff->nhis_code);
})->with('random_nhis_prices');

it('uses NHIS Master price multiplied by quantity for insurance_pays', function (float $nhisTariffPrice, int $quantity) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => $nhisTariffPrice * 2, // Hospital price is different
    ]);

    // Create NHIS tariff
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $nhisTariffPrice,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
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

    // Assert: insurance_pays should equal NHIS tariff price * quantity
    $expectedInsurancePays = round($nhisTariffPrice * $quantity, 2);
    expect($result['is_covered'])->toBeTrue()
        ->and($result['insurance_pays'])->toBe($expectedInsurancePays)
        ->and($result['subtotal'])->toBe($nhisTariffPrice * $quantity);
})->with('random_nhis_prices')->with('random_quantities');

it('returns not covered when item is not mapped to NHIS', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug WITHOUT NHIS mapping
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
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

    // Assert: Should not be covered since no NHIS mapping exists
    expect($result['is_covered'])->toBeFalse()
        ->and($result['insurance_pays'])->toBe(0.00)
        ->and($result['patient_pays'])->toBe(100.00)
        ->and($result['coverage_type'])->toBe('nhis_not_mapped')
        ->and($result['is_nhis'])->toBeTrue()
        ->and($result['nhis_code'])->toBeNull();
});

it('uses standard coverage calculation for non-NHIS plans', function (float $nhisTariffPrice) {
    // Arrange: Create non-NHIS provider and plan
    $provider = InsuranceProvider::factory()->create(['is_nhis' => false]);
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);

    // Create a drug
    $hospitalPrice = 150.00;
    $drug = Drug::factory()->create([
        'unit_price' => $hospitalPrice,
    ]);

    // Create NHIS tariff and mapping (should be ignored for non-NHIS)
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $nhisTariffPrice,
    ]);

    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create a coverage rule with specific tariff
    $ruleTariff = 120.00;
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'full',
        'tariff_amount' => $ruleTariff,
        'patient_copay_amount' => 0,
    ]);

    // Act: Calculate coverage
    $service = app(InsuranceCoverageService::class);
    $result = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $hospitalPrice,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Should use rule tariff, not NHIS tariff
    expect($result['is_covered'])->toBeTrue()
        ->and($result['insurance_pays'])->toBe($ruleTariff)
        ->and($result['insurance_tariff'])->toBe($ruleTariff)
        ->and($result)->not->toHaveKey('is_nhis'); // Non-NHIS result doesn't have is_nhis key
})->with('random_nhis_prices');

it('correctly identifies NHIS plan via provider relationship', function () {
    // Arrange: Create NHIS provider
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create non-NHIS provider
    $regularProvider = InsuranceProvider::factory()->create(['is_nhis' => false]);
    $regularPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $regularProvider->id,
    ]);

    // Act & Assert
    $service = app(InsuranceCoverageService::class);

    expect($service->isNhisPlan($nhisPlan->id))->toBeTrue()
        ->and($service->isNhisPlan($regularPlan->id))->toBeFalse();
});
