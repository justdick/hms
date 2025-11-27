<?php

/**
 * Property-Based Test for NHIS Price Propagation
 *
 * **Feature: nhis-claims-integration, Property 3: NHIS Price Propagation**
 * **Validates: Requirements 1.6, 5.5**
 *
 * Property: For any NHIS tariff price update in the Master, all subsequent
 * coverage calculations for mapped items should use the new price.
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
 * Generate random price pairs (old price, new price) for property testing
 */
dataset('random_price_changes', function () {
    $changes = [];
    for ($i = 0; $i < 15; $i++) {
        $oldPrice = fake()->randomFloat(2, 50, 300);
        $newPrice = fake()->randomFloat(2, 50, 300);
        // Ensure prices are different
        while (abs($oldPrice - $newPrice) < 0.01) {
            $newPrice = fake()->randomFloat(2, 50, 300);
        }
        $changes[] = [$oldPrice, $newPrice];
    }

    return $changes;
});

it('uses updated NHIS tariff price after Master update', function (float $oldPrice, float $newPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 200.00,
    ]);

    // Create NHIS tariff with OLD price
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $oldPrice,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create coverage rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'full',
        'patient_copay_amount' => 5.00,
    ]);

    $service = app(InsuranceCoverageService::class);

    // Act 1: Calculate coverage with OLD price
    $resultBefore = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Uses old price
    expect($resultBefore['insurance_pays'])->toBe(round($oldPrice, 2))
        ->and($resultBefore['insurance_tariff'])->toBe($oldPrice);

    // Act 2: Update NHIS tariff price in Master
    $nhisTariff->update(['price' => $newPrice]);

    // Act 3: Calculate coverage with NEW price
    $resultAfter = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Uses new price after update
    expect($resultAfter['insurance_pays'])->toBe(round($newPrice, 2))
        ->and($resultAfter['insurance_tariff'])->toBe($newPrice)
        ->and($resultAfter['insurance_pays'])->not->toBe($resultBefore['insurance_pays']);
})->with('random_price_changes');

it('propagates price changes to all items mapped to the same tariff', function (float $oldPrice, float $newPrice) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create multiple drugs
    $drug1 = Drug::factory()->create(['unit_price' => 100.00]);
    $drug2 = Drug::factory()->create(['unit_price' => 150.00]);

    // Create ONE NHIS tariff that both drugs map to
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $oldPrice,
    ]);

    // Create mappings for both drugs to the same tariff
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug1->id,
        'item_code' => $drug1->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug2->id,
        'item_code' => $drug2->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create coverage rules
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => null, // General rule
        'is_covered' => true,
        'coverage_type' => 'full',
        'patient_copay_amount' => 0,
    ]);

    $service = app(InsuranceCoverageService::class);

    // Act 1: Calculate coverage for both drugs with OLD price
    $result1Before = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug1->drug_code,
        amount: $drug1->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug1->id,
        itemType: 'drug'
    );

    $result2Before = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug2->drug_code,
        amount: $drug2->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug2->id,
        itemType: 'drug'
    );

    // Assert: Both use old price
    expect($result1Before['insurance_pays'])->toBe(round($oldPrice, 2))
        ->and($result2Before['insurance_pays'])->toBe(round($oldPrice, 2));

    // Act 2: Update NHIS tariff price in Master
    $nhisTariff->update(['price' => $newPrice]);

    // Act 3: Calculate coverage for both drugs with NEW price
    $result1After = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug1->drug_code,
        amount: $drug1->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug1->id,
        itemType: 'drug'
    );

    $result2After = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug2->drug_code,
        amount: $drug2->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug2->id,
        itemType: 'drug'
    );

    // Assert: Both use new price after update
    expect($result1After['insurance_pays'])->toBe(round($newPrice, 2))
        ->and($result2After['insurance_pays'])->toBe(round($newPrice, 2));
})->with('random_price_changes');

it('price changes affect subtotal calculation correctly', function (float $oldPrice, float $newPrice) {
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
        'price' => $oldPrice,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    $service = app(InsuranceCoverageService::class);
    $quantity = 3;

    // Act 1: Calculate with old price
    $resultBefore = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: $quantity,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Subtotal uses old price * quantity
    expect($resultBefore['subtotal'])->toBe($oldPrice * $quantity);

    // Act 2: Update price
    $nhisTariff->update(['price' => $newPrice]);

    // Act 3: Calculate with new price
    $resultAfter = $service->calculateCoverage(
        insurancePlanId: $nhisPlan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: $quantity,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Subtotal uses new price * quantity
    expect($resultAfter['subtotal'])->toBe($newPrice * $quantity);
})->with('random_price_changes');

it('price changes do not affect non-NHIS plans', function (float $oldPrice, float $newPrice) {
    // Arrange: Create non-NHIS provider and plan
    $provider = InsuranceProvider::factory()->create(['is_nhis' => false]);
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 200.00,
    ]);

    // Create NHIS tariff (even though plan is not NHIS)
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $oldPrice,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Create coverage rule with specific tariff
    $ruleTariff = 150.00;
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'is_covered' => true,
        'coverage_type' => 'full',
        'tariff_amount' => $ruleTariff,
        'patient_copay_amount' => 0,
    ]);

    $service = app(InsuranceCoverageService::class);

    // Act 1: Calculate with old NHIS price
    $resultBefore = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Uses rule tariff, not NHIS price
    expect($resultBefore['insurance_pays'])->toBe($ruleTariff);

    // Act 2: Update NHIS tariff price
    $nhisTariff->update(['price' => $newPrice]);

    // Act 3: Calculate again
    $resultAfter = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: $drug->drug_code,
        amount: $drug->unit_price,
        quantity: 1,
        date: null,
        itemId: $drug->id,
        itemType: 'drug'
    );

    // Assert: Still uses rule tariff, NHIS price change has no effect
    expect($resultAfter['insurance_pays'])->toBe($ruleTariff)
        ->and($resultAfter['insurance_pays'])->toBe($resultBefore['insurance_pays']);
})->with('random_price_changes');
