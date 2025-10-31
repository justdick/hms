<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\InsuranceTariff;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Services\InsuranceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can verify patient eligibility for active insurance', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subDays(10),
        'coverage_end_date' => now()->addDays(100),
    ]);

    $eligibility = $service->verifyEligibility($patient->id);

    expect($eligibility)->not->toBeNull()
        ->and($eligibility->id)->toBe($patientInsurance->id)
        ->and($eligibility->plan)->not->toBeNull();
});

test('returns null for patient without active insurance', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();

    $eligibility = $service->verifyEligibility($patient->id);

    expect($eligibility)->toBeNull();
});

test('returns null for expired insurance', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subDays(100),
        'coverage_end_date' => now()->subDays(10),
    ]);

    $eligibility = $service->verifyEligibility($patient->id);

    expect($eligibility)->toBeNull();
});

test('calculates full coverage correctly', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create a coverage rule for full coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null, // Applies to all consultation items
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        $patientInsurance,
        'consultation',
        'CONSULT-001',
        100.00,
        1
    );

    expect($coverage['is_covered'])->toBeTrue()
        ->and($coverage['coverage_type'])->toBe('full')
        ->and($coverage['coverage_percentage'])->toBe(100.00)
        ->and($coverage['insurance_pays'])->toBe(100.00)
        ->and($coverage['patient_pays'])->toBe(0.00);
});

test('calculates percentage coverage correctly', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create a coverage rule for 80% coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        $patientInsurance,
        'drug',
        'DRUG-001',
        100.00,
        1
    );

    expect($coverage['is_covered'])->toBeTrue()
        ->and($coverage['coverage_type'])->toBe('percentage')
        ->and($coverage['coverage_percentage'])->toBe(80.00)
        ->and($coverage['insurance_pays'])->toBe(80.00)
        ->and($coverage['patient_pays'])->toBe(20.00);
});

test('calculates coverage with patient copay percentage', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create a coverage rule with 10% patient copay
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'patient_copay_percentage' => 10.00,
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        $patientInsurance,
        'lab',
        'LAB-001',
        100.00,
        1
    );

    expect($coverage['is_covered'])->toBeTrue()
        ->and($coverage['coverage_percentage'])->toBe(90.00)
        ->and($coverage['insurance_pays'])->toBe(90.00)
        ->and($coverage['patient_pays'])->toBe(10.00);
});

test('returns no coverage for excluded items', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create a coverage rule excluding specific item
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG-EXCLUDED',
        'is_covered' => false,
        'coverage_type' => 'excluded',
        'is_active' => true,
    ]);

    $coverage = $service->calculateCoverage(
        $patientInsurance,
        'drug',
        'DRUG-EXCLUDED',
        100.00,
        1
    );

    expect($coverage['is_covered'])->toBeFalse()
        ->and($coverage['insurance_pays'])->toBe(0.00)
        ->and($coverage['patient_pays'])->toBe(100.00);
});

test('uses insurance tariff when available', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    // Create a tariff with negotiated price
    InsuranceTariff::factory()->create([
        'insurance_plan_id' => $plan->id,
        'item_type' => 'consultation',
        'item_code' => 'CONSULT-001',
        'standard_price' => 100.00,
        'insurance_tariff' => 80.00, // Negotiated lower price
        'effective_from' => now()->subDays(10),
        'effective_to' => now()->addDays(100),
    ]);

    $coverage = $service->calculateCoverage(
        $patientInsurance,
        'consultation',
        'CONSULT-001',
        100.00, // Standard price
        1
    );

    expect($coverage['insurance_tariff'])->toBe(80.00)
        ->and($coverage['subtotal'])->toBe(80.00) // Uses negotiated tariff
        ->and($coverage['insurance_pays'])->toBe(80.00);
});

test('checks annual limit correctly', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
        'annual_limit' => 10000.00,
    ]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $limit = $service->checkAnnualLimit($patientInsurance);

    expect($limit['has_limit'])->toBeTrue()
        ->and($limit['annual_limit'])->toBe(10000.00)
        ->and($limit['used_amount'])->toBe(0.00)
        ->and($limit['remaining_amount'])->toBe(10000.00)
        ->and($limit['is_exceeded'])->toBeFalse();
});

test('batch calculates coverage for multiple items', function () {
    $service = new InsuranceService;

    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Full coverage for consultations
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    // 80% coverage for drugs
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    $items = [
        ['type' => 'consultation', 'code' => 'CONSULT-001', 'price' => 100.00, 'quantity' => 1],
        ['type' => 'drug', 'code' => 'DRUG-001', 'price' => 50.00, 'quantity' => 2],
    ];

    $result = $service->calculateBatchCoverage($patientInsurance, $items);

    expect($result['summary']['total_subtotal'])->toBe(200.00) // 100 + (50*2)
        ->and($result['summary']['total_insurance_pays'])->toBe(180.00) // 100 + (100*0.8)
        ->and($result['summary']['total_patient_pays'])->toBe(20.00); // 0 + (100*0.2)
});
