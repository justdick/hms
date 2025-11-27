<?php

/**
 * Property-Based Test for NHIS CSV Import Saves Only Copay
 *
 * **Feature: nhis-claims-integration, Property 12: NHIS CSV Import Saves Only Copay**
 * **Validates: Requirements 6.3, 6.4**
 *
 * Property: For any NHIS coverage CSV import, only the copay amount should be saved
 * to the coverage rule. The tariff values in the CSV should be ignored.
 */

use App\Imports\NhisCoverageImport;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;

beforeEach(function () {
    // Clean up existing data
    InsuranceCoverageRule::query()->delete();
    NhisItemMapping::query()->delete();
    NhisTariff::query()->delete();
    Drug::query()->delete();
});

/**
 * Generate random copay amounts for property testing
 */
dataset('random_copay_amounts', function () {
    $amounts = [];
    for ($i = 0; $i < 10; $i++) {
        $amounts[] = [fake()->randomFloat(2, 0, 50)];
    }

    return $amounts;
});

/**
 * Generate random tariff values that should be ignored
 */
dataset('random_tariff_values', function () {
    $values = [];
    for ($i = 0; $i < 10; $i++) {
        $values[] = [fake()->randomFloat(2, 10, 500)];
    }

    return $values;
});

it('saves only copay amount from CSV import, ignoring tariff values', function (float $copayAmount, float $csvTariffValue) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);

    // Create NHIS tariff with a specific price (the "real" Master price)
    $masterPrice = 75.00;
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $masterPrice,
        'is_active' => true,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Prepare CSV data with a DIFFERENT tariff value (should be ignored)
    $csvRows = [
        [
            'item_code' => $drug->drug_code,
            'item_name' => $drug->name,
            'hospital_price' => '100.00',
            'nhis_tariff_price' => number_format($csvTariffValue, 2, '.', ''), // This should be IGNORED
            'copay_amount' => number_format($copayAmount, 2, '.', ''),
        ],
    ];

    // Act: Import the CSV data
    $importer = new NhisCoverageImport($nhisPlan, 'drug');
    $results = $importer->processRows($csvRows);

    // Assert: Only copay should be saved
    $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisPlan->id)
        ->where('coverage_category', 'drug')
        ->where('item_code', $drug->drug_code)
        ->first();

    expect($rule)->not->toBeNull('Coverage rule should be created');
    expect((float) $rule->patient_copay_amount)->toBe(round($copayAmount, 2), 'Copay amount should be saved');

    // The tariff_amount should NOT be set from CSV (it comes from Master)
    // For NHIS, we don't store tariff_amount in the rule - it's looked up from Master
    expect($rule->tariff_amount)->toBeNull('Tariff amount should not be saved from CSV');

    // Verify the import was successful
    expect($results['created'] + $results['updated'])->toBe(1);
})->with('random_copay_amounts')->with('random_tariff_values');

it('ignores tariff values even when they differ significantly from Master', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);

    // Create NHIS tariff with Master price
    $masterPrice = 50.00;
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'price' => $masterPrice,
        'is_active' => true,
    ]);

    // Create mapping
    NhisItemMapping::factory()->create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Prepare CSV data with wildly different tariff value
    $csvRows = [
        [
            'item_code' => $drug->drug_code,
            'item_name' => $drug->name,
            'hospital_price' => '100.00',
            'nhis_tariff_price' => '999.99', // Completely different - should be ignored
            'copay_amount' => '10.00',
        ],
    ];

    // Act: Import the CSV data
    $importer = new NhisCoverageImport($nhisPlan, 'drug');
    $importer->processRows($csvRows);

    // Assert: Tariff should NOT be saved
    $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisPlan->id)
        ->where('coverage_category', 'drug')
        ->where('item_code', $drug->drug_code)
        ->first();

    expect($rule)->not->toBeNull();
    expect($rule->tariff_amount)->toBeNull('Tariff from CSV should be ignored');
    expect((float) $rule->patient_copay_amount)->toBe(10.00, 'Only copay should be saved');
});

it('updates existing rule copay without affecting other fields', function (float $newCopayAmount) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);

    // Create existing coverage rule with some copay
    $existingRule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $nhisPlan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'patient_copay_amount' => 5.00,
        'coverage_type' => 'full',
        'coverage_value' => 100,
        'notes' => 'Original notes',
    ]);

    // Prepare CSV data with new copay
    $csvRows = [
        [
            'item_code' => $drug->drug_code,
            'item_name' => $drug->name,
            'hospital_price' => '100.00',
            'nhis_tariff_price' => '75.00',
            'copay_amount' => number_format($newCopayAmount, 2, '.', ''),
        ],
    ];

    // Act: Import the CSV data
    $importer = new NhisCoverageImport($nhisPlan, 'drug');
    $results = $importer->processRows($csvRows);

    // Assert: Only copay should be updated
    $rule = InsuranceCoverageRule::find($existingRule->id);

    expect($rule)->not->toBeNull();
    expect((float) $rule->patient_copay_amount)->toBe(round($newCopayAmount, 2), 'Copay should be updated');
    expect($results['updated'])->toBe(1, 'Should count as update');
})->with('random_copay_amounts');

it('handles empty copay values correctly', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);

    // Prepare CSV data with empty copay
    $csvRows = [
        [
            'item_code' => $drug->drug_code,
            'item_name' => $drug->name,
            'hospital_price' => '100.00',
            'nhis_tariff_price' => '75.00',
            'copay_amount' => '', // Empty copay
        ],
    ];

    // Act: Import the CSV data
    $importer = new NhisCoverageImport($nhisPlan, 'drug');
    $results = $importer->processRows($csvRows);

    // Assert: Should be skipped (no rule created for empty copay)
    expect($results['skipped'])->toBe(1, 'Empty copay should be skipped');
});

it('handles NOT MAPPED tariff value correctly', function (float $copayAmount) {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create a drug (without NHIS mapping)
    $drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);

    // Prepare CSV data with "NOT MAPPED" tariff
    $csvRows = [
        [
            'item_code' => $drug->drug_code,
            'item_name' => $drug->name,
            'hospital_price' => '100.00',
            'nhis_tariff_price' => 'NOT MAPPED', // Should be ignored
            'copay_amount' => number_format($copayAmount, 2, '.', ''),
        ],
    ];

    // Act: Import the CSV data
    $importer = new NhisCoverageImport($nhisPlan, 'drug');
    $results = $importer->processRows($csvRows);

    // Assert: Copay should still be saved even for unmapped items
    $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisPlan->id)
        ->where('coverage_category', 'drug')
        ->where('item_code', $drug->drug_code)
        ->first();

    expect($rule)->not->toBeNull('Rule should be created even for unmapped items');
    expect((float) $rule->patient_copay_amount)->toBe(round($copayAmount, 2), 'Copay should be saved');
    expect($rule->tariff_amount)->toBeNull('Tariff should not be saved');
})->with('random_copay_amounts');

it('processes multiple rows correctly, saving only copay for each', function () {
    // Arrange: Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
    ]);

    // Create multiple drugs with different copays
    $testCases = [];
    $csvRows = [];

    for ($i = 0; $i < 5; $i++) {
        $drug = Drug::factory()->create([
            'unit_price' => fake()->randomFloat(2, 50, 200),
            'is_active' => true,
        ]);

        $copayAmount = fake()->randomFloat(2, 5, 30);
        $csvTariffValue = fake()->randomFloat(2, 20, 100); // Should be ignored

        $testCases[$drug->drug_code] = $copayAmount;

        $csvRows[] = [
            'item_code' => $drug->drug_code,
            'item_name' => $drug->name,
            'hospital_price' => number_format($drug->unit_price, 2, '.', ''),
            'nhis_tariff_price' => number_format($csvTariffValue, 2, '.', ''),
            'copay_amount' => number_format($copayAmount, 2, '.', ''),
        ];
    }

    // Act: Import the CSV data
    $importer = new NhisCoverageImport($nhisPlan, 'drug');
    $results = $importer->processRows($csvRows);

    // Assert: All copays should be saved correctly
    expect($results['created'])->toBe(5, 'All 5 rules should be created');

    foreach ($testCases as $itemCode => $expectedCopay) {
        $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisPlan->id)
            ->where('coverage_category', 'drug')
            ->where('item_code', $itemCode)
            ->first();

        expect($rule)->not->toBeNull("Rule for {$itemCode} should exist");
        expect((float) $rule->patient_copay_amount)->toBe(round($expectedCopay, 2), "Copay for {$itemCode} should match");
        expect($rule->tariff_amount)->toBeNull("Tariff for {$itemCode} should not be saved");
    }
});
