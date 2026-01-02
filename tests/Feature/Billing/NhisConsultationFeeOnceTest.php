<?php

use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\DepartmentBilling;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Services\BillingService;

beforeEach(function () {
    $this->billingService = app(BillingService::class);

    // Create NHIS provider and plan
    $this->nhisProvider = InsuranceProvider::factory()->create([
        'is_nhis' => true,
        'is_active' => true,
    ]);

    $this->nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->nhisProvider->id,
        'is_active' => true,
    ]);

    // Create department billing
    $this->departmentBilling = DepartmentBilling::factory()->create([
        'consultation_fee' => 40.00,
        'is_active' => true,
    ]);
});

it('charges consultation fee on first visit for NHIS patient when config enabled', function () {
    // Enable the config
    BillingConfiguration::setValue('nhis_consultation_fee_once_per_lifetime', true, 'nhis');
    BillingConfiguration::setValue('auto_billing_enabled', true, 'general');

    // Create NHIS patient
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->nhisPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    // First check-in
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);

    $charge = $this->billingService->createConsultationCharge($checkin);

    expect($charge)->not->toBeNull()
        ->and($charge->charge_type)->toBe('consultation_fee')
        ->and((float) $charge->amount)->toBe(40.00);
});

it('skips consultation fee on subsequent visits for NHIS patient when config enabled', function () {
    // Enable the config
    BillingConfiguration::setValue('nhis_consultation_fee_once_per_lifetime', true, 'nhis');
    BillingConfiguration::setValue('auto_billing_enabled', true, 'general');

    // Create NHIS patient
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->nhisPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    // First check-in - should create charge
    $firstCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $firstCharge = $this->billingService->createConsultationCharge($firstCheckin);
    expect($firstCharge)->not->toBeNull();

    // Second check-in - should NOT create charge
    $secondCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $secondCharge = $this->billingService->createConsultationCharge($secondCheckin);

    expect($secondCharge)->toBeNull();

    // Count charges for this specific patient only
    $patientChargeCount = Charge::whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
        ->where('charge_type', 'consultation_fee')
        ->count();
    expect($patientChargeCount)->toBe(1);
});

it('charges consultation fee every visit for NHIS patient when config disabled', function () {
    // Disable the config
    BillingConfiguration::setValue('nhis_consultation_fee_once_per_lifetime', false, 'nhis');
    BillingConfiguration::setValue('auto_billing_enabled', true, 'general');

    // Create NHIS patient
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->nhisPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    // First check-in
    $firstCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $firstCharge = $this->billingService->createConsultationCharge($firstCheckin);

    // Second check-in
    $secondCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $secondCharge = $this->billingService->createConsultationCharge($secondCheckin);

    expect($firstCharge)->not->toBeNull()
        ->and($secondCharge)->not->toBeNull();

    // Count charges for this specific patient only
    $patientChargeCount = Charge::whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
        ->where('charge_type', 'consultation_fee')
        ->count();
    expect($patientChargeCount)->toBe(2);
});

it('charges consultation fee every visit for non-NHIS patient regardless of config', function () {
    // Enable the config
    BillingConfiguration::setValue('nhis_consultation_fee_once_per_lifetime', true, 'nhis');
    BillingConfiguration::setValue('auto_billing_enabled', true, 'general');

    // Create non-NHIS provider and plan
    $privateProvider = InsuranceProvider::factory()->create([
        'is_nhis' => false,
        'is_active' => true,
    ]);
    $privatePlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $privateProvider->id,
        'is_active' => true,
    ]);

    // Create patient with private insurance
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $privatePlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    // First check-in
    $firstCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $firstCharge = $this->billingService->createConsultationCharge($firstCheckin);

    // Second check-in
    $secondCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $secondCharge = $this->billingService->createConsultationCharge($secondCheckin);

    expect($firstCharge)->not->toBeNull()
        ->and($secondCharge)->not->toBeNull();

    // Count charges for this specific patient only
    $patientChargeCount = Charge::whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
        ->where('charge_type', 'consultation_fee')
        ->count();
    expect($patientChargeCount)->toBe(2);
});

it('charges consultation fee every visit for cash patient regardless of config', function () {
    // Enable the config
    BillingConfiguration::setValue('nhis_consultation_fee_once_per_lifetime', true, 'nhis');
    BillingConfiguration::setValue('auto_billing_enabled', true, 'general');

    // Create patient without any insurance
    $patient = Patient::factory()->create();

    // First check-in
    $firstCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $firstCharge = $this->billingService->createConsultationCharge($firstCheckin);

    // Second check-in
    $secondCheckin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->departmentBilling->department_id,
    ]);
    $secondCharge = $this->billingService->createConsultationCharge($secondCheckin);

    expect($firstCharge)->not->toBeNull()
        ->and($secondCharge)->not->toBeNull();

    // Count charges for this specific patient only
    $patientChargeCount = Charge::whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
        ->where('charge_type', 'consultation_fee')
        ->count();
    expect($patientChargeCount)->toBe(2);
});
