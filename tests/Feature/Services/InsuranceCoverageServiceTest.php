<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\InsuranceTariff;
use App\Services\InsuranceCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('specific rule takes precedence over general rule', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Create general rule: 80% coverage for all drugs
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create specific rule: 100% coverage for Paracetamol
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: 'DRUG001',
        amount: 100.00
    );

    expect($coverage['is_covered'])->toBeTrue()
        ->and($coverage['rule_type'])->toBe('specific')
        ->and($coverage['coverage_type'])->toBe('full')
        ->and($coverage['coverage_percentage'])->toBe(100.00)
        ->and($coverage['insurance_pays'])->toBe(100.00)
        ->and($coverage['patient_pays'])->toBe(0.00);
});

test('general rule fallback when no specific rule exists', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Create general rule: 80% coverage for all drugs
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'patient_copay_percentage' => 0.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: 'DRUG999',
        amount: 100.00
    );

    expect($coverage['is_covered'])->toBeTrue()
        ->and($coverage['rule_type'])->toBe('general')
        ->and($coverage['coverage_type'])->toBe('percentage')
        ->and($coverage['coverage_percentage'])->toBe(80.00)
        ->and($coverage['insurance_pays'])->toBe(80.00)
        ->and($coverage['patient_pays'])->toBe(20.00);
});

test('no coverage when no rules exist', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $coverage = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: 'DRUG999',
        amount: 100.00
    );

    expect($coverage['is_covered'])->toBeFalse()
        ->and($coverage['rule_type'])->toBe('none')
        ->and($coverage['coverage_percentage'])->toBe(0.00)
        ->and($coverage['insurance_pays'])->toBe(0.00)
        ->and($coverage['patient_pays'])->toBe(100.00);
});

test('effective date filtering works correctly', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Create rule that is not yet effective
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'effective_from' => now()->addDays(10),
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        insurancePlanId: $plan->id,
        category: 'drug',
        itemCode: 'DRUG001',
        amount: 100.00
    );

    expect($coverage['is_covered'])->toBeFalse()
        ->and($coverage['rule_type'])->toBe('none');
});

test('coverage calculation for all coverage types', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Test full coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'item_code' => 'CONSULT001',
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'consultation', 'CONSULT001', 100.00);
    expect($coverage['insurance_pays'])->toBe(100.00)
        ->and($coverage['patient_pays'])->toBe(0.00);

    // Test percentage coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 75.00,
        'patient_copay_percentage' => 0.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'drug', 'DRUG001', 100.00);
    expect($coverage['insurance_pays'])->toBe(75.00)
        ->and($coverage['patient_pays'])->toBe(25.00);

    // Test fixed coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'item_code' => 'LAB001',
        'is_covered' => true,
        'coverage_type' => 'fixed',
        'coverage_value' => 50.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'lab', 'LAB001', 100.00);
    expect($coverage['insurance_pays'])->toBe(50.00)
        ->and($coverage['patient_pays'])->toBe(50.00);

    // Test excluded coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'procedure',
        'item_code' => 'PROC001',
        'is_covered' => false,
        'coverage_type' => 'excluded',
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'procedure', 'PROC001', 100.00);
    expect($coverage['insurance_pays'])->toBe(0.00)
        ->and($coverage['patient_pays'])->toBe(100.00);
});

test('caches coverage rules', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // First call should cache the rule
    $service->getCoverageRule($plan->id, 'drug', 'DRUG001');

    // Check that cache key exists
    $cacheKey = "coverage_rule_general_{$plan->id}_drug";
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('cache is cleared when rule is saved', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Manually set cache
    $cacheKey = "coverage_rule_general_{$plan->id}_drug";
    Cache::put($cacheKey, $rule, 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    // Update the rule
    $rule->update(['coverage_value' => 90.00]);

    // Cache should be cleared
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('cache is cleared when rule is deleted', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    // Manually set cache
    $cacheKey = "coverage_rule_specific_{$plan->id}_drug_DRUG001";
    Cache::put($cacheKey, $rule, 3600);

    expect(Cache::has($cacheKey))->toBeTrue();

    // Delete the rule
    $rule->delete();

    // Cache should be cleared
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('uses insurance tariff when available', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    // Create tariff with negotiated price
    InsuranceTariff::factory()->create([
        'insurance_plan_id' => $plan->id,
        'item_type' => 'drug',
        'item_code' => 'DRUG001',
        'standard_price' => 100.00,
        'insurance_tariff' => 80.00,
        'effective_from' => now()->subDays(10),
        'effective_to' => now()->addDays(100),
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'drug', 'DRUG001', 100.00);

    expect($coverage['insurance_tariff'])->toEqual(80.00)
        ->and($coverage['subtotal'])->toBe(80.00)
        ->and($coverage['insurance_pays'])->toBe(80.00);
});

test('calculates coverage with quantity', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'patient_copay_percentage' => 0.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'drug', 'DRUG001', 50.00, 3);

    expect($coverage['subtotal'])->toBe(150.00)
        ->and($coverage['insurance_pays'])->toBe(120.00)
        ->and($coverage['patient_pays'])->toBe(30.00);
});

test('respects max quantity per visit limit', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'max_quantity_per_visit' => 2,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'drug', 'DRUG001', 50.00, 5);

    expect($coverage['exceeded_limit'])->toBeTrue()
        ->and($coverage['limit_message'])->toContain('exceeds plan limit of 2');
});

test('respects max amount per visit limit', function () {
    $service = new InsuranceCoverageService;

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'max_amount_per_visit' => 100.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage($plan->id, 'drug', 'DRUG001', 200.00);

    expect($coverage['exceeded_limit'])->toBeTrue()
        ->and($coverage['insurance_pays'])->toBe(100.00)
        ->and($coverage['patient_pays'])->toBe(100.00);
});

test('model scopes work correctly', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Create general rule
    $generalRule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_active' => true,
    ]);

    // Create specific rule
    $specificRule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'is_active' => true,
    ]);

    // Test general scope
    $generalRules = InsuranceCoverageRule::general()->get();
    expect($generalRules)->toHaveCount(1)
        ->and($generalRules->first()->id)->toBe($generalRule->id);

    // Test specific scope
    $specificRules = InsuranceCoverageRule::specific()->get();
    expect($specificRules)->toHaveCount(1)
        ->and($specificRules->first()->id)->toBe($specificRule->id);

    // Test forCategory scope
    $drugRules = InsuranceCoverageRule::forCategory('drug')->get();
    expect($drugRules)->toHaveCount(2);

    // Test active scope
    $activeRules = InsuranceCoverageRule::active()->get();
    expect($activeRules)->toHaveCount(2);
});

test('model accessors work correctly', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $generalRule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
    ]);

    $specificRule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
    ]);

    expect($generalRule->is_general)->toBeTrue()
        ->and($generalRule->is_specific)->toBeFalse()
        ->and($generalRule->rule_type)->toBe('general');

    expect($specificRule->is_general)->toBeFalse()
        ->and($specificRule->is_specific)->toBeTrue()
        ->and($specificRule->rule_type)->toBe('specific');
});
