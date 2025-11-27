<?php

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Services\InsuranceCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(InsuranceCoverageService::class);
});

it('uses tariff amount when set instead of standard price', function () {
    $plan = InsurancePlan::factory()->create();
    $drug = Drug::factory()->create(['unit_price' => 20.00]);

    // Create rule with tariff
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'coverage_type' => 'percentage',
        'coverage_value' => 100,
        'tariff_amount' => 10.00, // Insurance negotiated lower price
        'patient_copay_amount' => 0,
    ]);

    $result = $this->service->calculateCoverage(
        $plan->id,
        'drug',
        $drug->drug_code,
        $drug->unit_price,
        1
    );

    expect($result['insurance_tariff'])->toBe(10.00)
        ->and($result['insurance_pays'])->toBe(10.00)
        ->and($result['patient_pays'])->toBe(0.00);
});

it('calculates tariff with fixed copay correctly', function () {
    $plan = InsurancePlan::factory()->create();
    $drug = Drug::factory()->create(['unit_price' => 20.00]);

    // Tariff: 10, Coverage: 100%, Copay: 15
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'coverage_type' => 'percentage',
        'coverage_value' => 100,
        'tariff_amount' => 10.00,
        'patient_copay_amount' => 15.00,
    ]);

    $result = $this->service->calculateCoverage(
        $plan->id,
        'drug',
        $drug->drug_code,
        $drug->unit_price,
        1
    );

    expect($result['insurance_pays'])->toBe(10.00)
        ->and($result['patient_pays'])->toBe(15.00); // Fixed copay
});

it('calculates standard price with percentage split', function () {
    $plan = InsurancePlan::factory()->create();
    $drug = Drug::factory()->create(['unit_price' => 20.00]);

    // No tariff, 80% coverage (patient pays remaining 20%)
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'tariff_amount' => null,
        'patient_copay_amount' => 0,
    ]);

    $result = $this->service->calculateCoverage(
        $plan->id,
        'drug',
        $drug->drug_code,
        $drug->unit_price,
        1
    );

    expect($result['insurance_tariff'])->toBe(20.00) // Uses standard price
        ->and($result['insurance_pays'])->toBe(16.00) // 80% of 20
        ->and($result['patient_pays'])->toBe(4.00); // 20% of 20
});

it('calculates standard price with percentage split plus additional copay', function () {
    $plan = InsurancePlan::factory()->create();
    $drug = Drug::factory()->create(['unit_price' => 20.00]);

    // No tariff, 80% coverage (patient pays 20%), plus 5 fixed copay
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'tariff_amount' => null,
        'patient_copay_amount' => 5.00,
    ]);

    $result = $this->service->calculateCoverage(
        $plan->id,
        'drug',
        $drug->drug_code,
        $drug->unit_price,
        1
    );

    expect($result['insurance_pays'])->toBe(16.00) // 80% of 20
        ->and($result['patient_pays'])->toBe(9.00); // 4 (20%) + 5 (copay)
});

it('applies copay per quantity', function () {
    $plan = InsurancePlan::factory()->create();
    $drug = Drug::factory()->create(['unit_price' => 10.00]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => $drug->drug_code,
        'coverage_type' => 'percentage',
        'coverage_value' => 100,
        'tariff_amount' => 8.00,
        'patient_copay_amount' => 2.00, // Per unit
    ]);

    $result = $this->service->calculateCoverage(
        $plan->id,
        'drug',
        $drug->drug_code,
        $drug->unit_price,
        5 // 5 units
    );

    expect($result['insurance_pays'])->toBe(40.00) // 8 * 5
        ->and($result['patient_pays'])->toBe(10.00); // 2 * 5
});
