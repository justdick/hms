<?php

use App\Models\Charge;
use App\Models\Department;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('charge is automatically linked to insurance claim when created', function () {
    // Setup
    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create coverage rule for full coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    // Create checkin with insurance claim
    $department = Department::factory()->create();
    $user = User::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'checked_in_by' => $user->id,
        'claim_check_code' => 'TEST-CCC-001',
    ]);

    $claim = InsuranceClaim::create([
        'claim_check_code' => 'TEST-CCC-001',
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'patient_checkin_id' => $checkin->id,
        'date_of_attendance' => now()->toDateString(),
        'type_of_service' => 'outpatient',
        'status' => 'draft',
    ]);

    // Create charge - should auto-link to insurance
    $charge = Charge::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'consultation',
        'service_code' => 'CONSULT-001',
        'description' => 'General Consultation',
        'amount' => 100.00,
        'charge_type' => 'consultation_fee',
        'charged_at' => now(),
        'created_by_type' => 'App\Models\User',
        'created_by_id' => $user->id,
        'status' => 'pending',
    ]);

    // Assertions
    expect($charge->is_insurance_claim)->toBeTrue()
        ->and($charge->insurance_claim_id)->toBe($claim->id)
        ->and($charge->insurance_covered_amount)->toBe(100.00)
        ->and($charge->patient_copay_amount)->toBe(0.00);

    // Check that claim item was created
    $claimItem = InsuranceClaimItem::where('charge_id', $charge->id)->first();
    expect($claimItem)->not->toBeNull()
        ->and($claimItem->insurance_claim_id)->toBe($claim->id)
        ->and($claimItem->item_type)->toBe('consultation')
        ->and($claimItem->insurance_pays)->toBe(100.00)
        ->and($claimItem->patient_pays)->toBe(0.00);

    // Check that claim totals were updated
    $claim->refresh();
    expect($claim->total_claim_amount)->toBe(100.00)
        ->and($claim->insurance_covered_amount)->toBe(100.00)
        ->and($claim->patient_copay_amount)->toBe(0.00);
});

test('charge calculates partial coverage correctly', function () {
    // Setup
    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create coverage rule for 80% coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create checkin with insurance claim
    $department = Department::factory()->create();
    $user = User::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'checked_in_by' => $user->id,
        'claim_check_code' => 'TEST-CCC-002',
    ]);

    $claim = InsuranceClaim::create([
        'claim_check_code' => 'TEST-CCC-002',
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'patient_checkin_id' => $checkin->id,
        'date_of_attendance' => now()->toDateString(),
        'type_of_service' => 'outpatient',
        'status' => 'draft',
    ]);

    // Create charge for pharmacy
    $charge = Charge::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'pharmacy',
        'service_code' => 'DRUG-001',
        'description' => 'Paracetamol 500mg',
        'amount' => 50.00,
        'charge_type' => 'medication',
        'charged_at' => now(),
        'created_by_type' => 'App\Models\User',
        'created_by_id' => $user->id,
        'status' => 'pending',
    ]);

    // Assertions
    expect($charge->is_insurance_claim)->toBeTrue()
        ->and($charge->insurance_covered_amount)->toBe(40.00) // 80% of 50
        ->and($charge->patient_copay_amount)->toBe(10.00); // 20% of 50
});

test('charge without insurance claim is not auto-linked', function () {
    $patient = Patient::factory()->create();
    $department = Department::factory()->create();
    $user = User::factory()->create();

    // Create checkin WITHOUT claim_check_code
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'checked_in_by' => $user->id,
        'claim_check_code' => null, // No insurance
    ]);

    // Create charge
    $charge = Charge::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'consultation',
        'service_code' => 'CONSULT-001',
        'description' => 'General Consultation',
        'amount' => 100.00,
        'charge_type' => 'consultation_fee',
        'charged_at' => now(),
        'created_by_type' => 'App\Models\User',
        'created_by_id' => $user->id,
        'status' => 'pending',
    ]);

    $charge->refresh();

    // Assertions
    expect($charge->is_insurance_claim)->toBeFalse()
        ->and($charge->insurance_claim_id)->toBeNull()
        ->and($charge->insurance_covered_amount)->toBe(0.00)
        ->and($charge->patient_copay_amount)->toBe(0.00);

    // Check that no claim item was created
    $claimItemCount = InsuranceClaimItem::where('charge_id', $charge->id)->count();
    expect($claimItemCount)->toBe(0);
});

test('updating charge amount recalculates insurance coverage', function () {
    // Setup
    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create coverage rule for 80% coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'is_active' => true,
    ]);

    // Create checkin with insurance claim
    $department = Department::factory()->create();
    $user = User::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'checked_in_by' => $user->id,
        'claim_check_code' => 'TEST-CCC-003',
    ]);

    $claim = InsuranceClaim::create([
        'claim_check_code' => 'TEST-CCC-003',
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'patient_checkin_id' => $checkin->id,
        'date_of_attendance' => now()->toDateString(),
        'type_of_service' => 'outpatient',
        'status' => 'draft',
    ]);

    // Create charge
    $charge = Charge::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'lab',
        'service_code' => 'LAB-001',
        'description' => 'Blood Test',
        'amount' => 100.00,
        'charge_type' => 'lab_test',
        'charged_at' => now(),
        'created_by_type' => 'App\Models\User',
        'created_by_id' => $user->id,
        'status' => 'pending',
    ]);

    // Initial state
    expect($charge->insurance_covered_amount)->toBe(80.00)
        ->and($charge->patient_copay_amount)->toBe(20.00);

    $claim->refresh();
    $initialTotal = $claim->total_claim_amount;

    // Update charge amount
    $charge->update(['amount' => 150.00]);

    // Check recalculation
    $charge->refresh();
    expect($charge->insurance_covered_amount)->toBe(120.00) // 80% of 150
        ->and($charge->patient_copay_amount)->toBe(30.00); // 20% of 150

    // Check claim totals were updated
    $claim->refresh();
    expect($claim->total_claim_amount)->toBe($initialTotal - 100.00 + 150.00);
});

test('deleting charge updates claim totals', function () {
    // Setup
    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create coverage rule for full coverage
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null,
        'is_covered' => true,
        'coverage_type' => 'full',
        'is_active' => true,
    ]);

    // Create checkin with insurance claim
    $department = Department::factory()->create();
    $user = User::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'checked_in_by' => $user->id,
        'claim_check_code' => 'TEST-CCC-004',
    ]);

    $claim = InsuranceClaim::create([
        'claim_check_code' => 'TEST-CCC-004',
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'patient_checkin_id' => $checkin->id,
        'date_of_attendance' => now()->toDateString(),
        'type_of_service' => 'outpatient',
        'status' => 'draft',
    ]);

    // Create two charges
    $charge1 = Charge::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'consultation',
        'service_code' => 'CONSULT-001',
        'description' => 'General Consultation',
        'amount' => 100.00,
        'charge_type' => 'consultation_fee',
        'charged_at' => now(),
        'created_by_type' => 'App\Models\User',
        'created_by_id' => $user->id,
        'status' => 'pending',
    ]);

    $charge2 = Charge::create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'consultation',
        'service_code' => 'CONSULT-002',
        'description' => 'Follow-up Consultation',
        'amount' => 50.00,
        'charge_type' => 'consultation_fee',
        'charged_at' => now(),
        'created_by_type' => 'App\Models\User',
        'created_by_id' => $user->id,
        'status' => 'pending',
    ]);

    $claim->refresh();
    expect($claim->total_claim_amount)->toBe(150.00)
        ->and($claim->insurance_covered_amount)->toBe(150.00);

    // Delete first charge
    $claimItemId = $charge1->insurance_claim_item_id;
    $charge1->delete();

    // Check that claim item was deleted
    $claimItem = InsuranceClaimItem::find($claimItemId);
    expect($claimItem)->toBeNull();

    // Check claim totals were updated
    $claim->refresh();
    expect($claim->total_claim_amount)->toBe(50.00)
        ->and($claim->insurance_covered_amount)->toBe(50.00);
});

test('charge maps service types to insurance item types correctly', function () {
    $patient = Patient::factory()->create();
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create coverage rules for different categories
    $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
    foreach ($categories as $category) {
        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $plan->id,
            'coverage_category' => $category,
            'item_code' => null,
            'is_covered' => true,
            'coverage_type' => 'full',
            'is_active' => true,
        ]);
    }

    $department = Department::factory()->create();
    $user = User::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'checked_in_by' => $user->id,
        'claim_check_code' => 'TEST-CCC-005',
    ]);

    $claim = InsuranceClaim::create([
        'claim_check_code' => 'TEST-CCC-005',
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'patient_checkin_id' => $checkin->id,
        'date_of_attendance' => now()->toDateString(),
        'type_of_service' => 'outpatient',
        'status' => 'draft',
    ]);

    // Test service type mappings
    $mappings = [
        'consultation' => 'consultation',
        'pharmacy' => 'drug',
        'lab' => 'lab',
        'procedure' => 'procedure',
        'ward' => 'ward',
        'nursing' => 'nursing',
    ];

    $chargeTypeMap = [
        'consultation' => 'consultation_fee',
        'pharmacy' => 'medication',
        'lab' => 'lab_test',
        'procedure' => 'procedure',
        'ward' => 'ward_bed',
        'nursing' => 'nursing_care',
    ];

    foreach ($mappings as $serviceType => $expectedItemType) {
        $charge = Charge::create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => $serviceType,
            'service_code' => strtoupper($serviceType).'-001',
            'description' => 'Test '.ucfirst($serviceType),
            'amount' => 100.00,
            'charge_type' => $chargeTypeMap[$serviceType],
            'charged_at' => now(),
            'created_by_type' => 'App\Models\User',
            'created_by_id' => $user->id,
            'status' => 'pending',
        ]);

        $claimItem = InsuranceClaimItem::where('charge_id', $charge->id)->first();
        expect($claimItem)->not->toBeNull()
            ->and($claimItem->item_type)->toBe($expectedItemType);
    }
});
