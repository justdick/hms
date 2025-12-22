<?php

use App\Models\Charge;
use App\Models\Department;
use App\Models\InsuranceClaim;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use App\Services\InsuranceApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions (use firstOrCreate to avoid duplicates)
    Permission::firstOrCreate(['name' => 'patients.update']);
    Permission::firstOrCreate(['name' => 'patients.view']);

    // Create admin role with all permissions (use firstOrCreate)
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $adminRole->givePermissionTo(['patients.update', 'patients.view']);

    // Create a user with necessary permissions
    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    // Create a department
    $this->department = Department::factory()->create();

    // Create insurance provider and plan with unique codes
    $uniqueCode = 'TEST'.uniqid();
    $this->provider = InsuranceProvider::factory()->create([
        'name' => 'Test Insurance',
        'code' => $uniqueCode,
    ]);

    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Basic Plan',
        'plan_code' => 'BASIC'.uniqid(),
        'is_active' => true,
    ]);

    // Create coverage rule for consultation
    InsuranceCoverageRule::create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null, // Default for all consultations
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
        'is_active' => true,
    ]);
});

test('insurance can be applied retroactively to active checkin', function () {
    // Create patient without insurance
    $patient = Patient::factory()->create();

    // Create checkin without insurance
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => null,
    ]);

    // Create a pending charge (e.g., consultation fee)
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'consultation',
        'service_code' => 'CONS001',
        'charge_type' => 'consultation_fee',
        'amount' => 100.00,
        'status' => 'pending',
        'is_insurance_claim' => false,
    ]);

    // Now add insurance to the patient
    $patientInsurance = PatientInsurance::create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'membership_id' => 'MEM123456',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
        'status' => 'active',
    ]);

    // Apply insurance retroactively
    $service = app(InsuranceApplicationService::class);
    $result = $service->applyInsuranceToActiveCheckin(
        $checkin,
        $patientInsurance,
        'CC-TEST-001'
    );

    expect($result['success'])->toBeTrue()
        ->and($result['charges_updated'])->toBe(1);

    // Verify checkin was updated
    $checkin->refresh();
    expect($checkin->claim_check_code)->toBe('CC-TEST-001');

    // Verify claim was created
    $claim = InsuranceClaim::where('claim_check_code', 'CC-TEST-001')->first();
    expect($claim)->not->toBeNull()
        ->and($claim->patient_id)->toBe($patient->id)
        ->and($claim->patient_insurance_id)->toBe($patientInsurance->id);

    // Verify charge was updated with insurance
    $charge->refresh();
    expect($charge->is_insurance_claim)->toBeTrue()
        ->and($charge->insurance_claim_id)->toBe($claim->id)
        ->and($charge->insurance_covered_amount)->toBeGreaterThan(0)
        ->and($charge->patient_copay_amount)->toBeGreaterThan(0);
});

test('cannot apply insurance to checkin that already has insurance', function () {
    $patient = Patient::factory()->create();

    // Create checkin WITH insurance
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => 'EXISTING-CCC',
    ]);

    $patientInsurance = PatientInsurance::create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'membership_id' => 'MEM123456',
        'coverage_start_date' => now()->subMonth(),
        'status' => 'active',
    ]);

    $service = app(InsuranceApplicationService::class);
    $result = $service->applyInsuranceToActiveCheckin(
        $checkin,
        $patientInsurance,
        'CC-NEW-001'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('already has insurance');
});

test('cannot apply insurance to completed checkin', function () {
    $patient = Patient::factory()->create();

    // Create completed checkin
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'completed',
        'claim_check_code' => null,
    ]);

    $patientInsurance = PatientInsurance::create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'membership_id' => 'MEM123456',
        'coverage_start_date' => now()->subMonth(),
        'status' => 'active',
    ]);

    $service = app(InsuranceApplicationService::class);
    $result = $service->applyInsuranceToActiveCheckin(
        $checkin,
        $patientInsurance,
        'CC-TEST-001'
    );

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('completed or cancelled');
});

test('patient update with new insurance applies to active checkin', function () {
    // Create patient without insurance
    $patient = Patient::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    // Create active checkin without insurance
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => null,
    ]);

    // Create a pending charge
    Charge::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'service_type' => 'consultation',
        'charge_type' => 'consultation_fee',
        'amount' => 100.00,
        'status' => 'pending',
        'is_insurance_claim' => false,
    ]);

    // Update patient with insurance
    $response = $this->actingAs($this->user)
        ->patch("/patients/{$patient->id}", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => true,
            'insurance_plan_id' => $this->plan->id,
            'membership_id' => 'MEM789',
            'coverage_start_date' => now()->subMonth()->format('Y-m-d'),
        ]);

    $response->assertRedirect();

    // Verify checkin now has insurance
    $checkin->refresh();
    expect($checkin->claim_check_code)->not->toBeNull();

    // Verify claim was created
    $claim = InsuranceClaim::where('patient_checkin_id', $checkin->id)->first();
    expect($claim)->not->toBeNull();
});

test('getActiveCheckinWithoutInsurance returns correct checkin', function () {
    $patient = Patient::factory()->create();

    // Create checkin without insurance
    $checkinWithoutInsurance = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => null,
    ]);

    $service = app(InsuranceApplicationService::class);
    $result = $service->getActiveCheckinWithoutInsurance($patient);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($checkinWithoutInsurance->id);
});

test('getActiveCheckinWithoutInsurance returns null when checkin has insurance', function () {
    $patient = Patient::factory()->create();

    // Create checkin WITH insurance
    PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => 'EXISTING-CCC',
    ]);

    $service = app(InsuranceApplicationService::class);
    $result = $service->getActiveCheckinWithoutInsurance($patient);

    expect($result)->toBeNull();
});

test('generateClaimCheckCode creates unique codes', function () {
    $service = app(InsuranceApplicationService::class);

    $code1 = $service->generateClaimCheckCode();
    $code2 = $service->generateClaimCheckCode();

    expect($code1)->toStartWith('CC-')
        ->and($code2)->toStartWith('CC-')
        ->and($code1)->not->toBe($code2);
});
