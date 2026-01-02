<?php

use App\Models\Charge;
use App\Models\Consultation;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\Ward;
use App\Models\WardBillingTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a ward billing template with NHIS pricing
    WardBillingTemplate::create([
        'service_name' => 'Daily Ward Fee',
        'service_code' => 'DAILY_WARD_FEE',
        'billing_type' => 'daily',
        'base_amount' => 100.00,
        'nhis_amount' => 0.00, // Free for NHIS patients
        'effective_from' => now()->subDay(),
        'effective_to' => null,
        'calculation_rules' => [
            'billing_starts' => 'midnight',
            'charge_on_admission_day' => true,
            'charge_on_discharge_day' => false,
        ],
        'auto_trigger_conditions' => [
            'trigger_on' => 'daily_schedule',
            'auto_create_charges' => true,
        ],
        'payment_requirement' => 'deferred',
        'is_active' => true,
    ]);
});

it('charges base amount for cash patients', function () {
    // Create a cash patient (no insurance)
    $patient = Patient::factory()->create();
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create(['patient_checkin_id' => $checkin->id]);
    $ward = Ward::factory()->create();

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
        'admitted_at' => now()->subDay(),
    ]);

    $this->artisan('admissions:generate-daily-charges')
        ->assertSuccessful();

    $charge = Charge::where('patient_checkin_id', $checkin->id)
        ->where('service_code', 'DAILY_WARD_FEE')
        ->first();

    expect($charge)->not->toBeNull();
    expect((float) $charge->amount)->toBe(100.00);
});

it('skips charge for NHIS patients when nhis_amount is zero', function () {
    // Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->create([
        'is_nhis' => true,
        'is_active' => true,
    ]);

    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
        'is_active' => true,
    ]);

    // Create patient with NHIS insurance
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create(['patient_checkin_id' => $checkin->id]);
    $ward = Ward::factory()->create();

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
        'admitted_at' => now()->subDay(),
    ]);

    $this->artisan('admissions:generate-daily-charges')
        ->assertSuccessful();

    // No charge should be created for NHIS patient when nhis_amount is 0
    $charge = Charge::where('patient_checkin_id', $checkin->id)
        ->where('service_code', 'DAILY_WARD_FEE')
        ->first();

    expect($charge)->toBeNull();
});

it('charges nhis_amount for NHIS patients when nhis_amount is set', function () {
    // Update template to have a non-zero NHIS amount
    WardBillingTemplate::where('service_code', 'DAILY_WARD_FEE')
        ->update(['nhis_amount' => 25.00]);

    // Create NHIS provider and plan
    $nhisProvider = InsuranceProvider::factory()->create([
        'is_nhis' => true,
        'is_active' => true,
    ]);

    $nhisPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $nhisProvider->id,
        'is_active' => true,
    ]);

    // Create patient with NHIS insurance
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create(['patient_checkin_id' => $checkin->id]);
    $ward = Ward::factory()->create();

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
        'admitted_at' => now()->subDay(),
    ]);

    $this->artisan('admissions:generate-daily-charges')
        ->assertSuccessful();

    $charge = Charge::where('patient_checkin_id', $checkin->id)
        ->where('service_code', 'DAILY_WARD_FEE')
        ->first();

    expect($charge)->not->toBeNull();
    expect((float) $charge->amount)->toBe(25.00);
});

it('uses base_amount for cash patients when nhis_amount differs', function () {
    // Update template to have different NHIS amount
    WardBillingTemplate::where('service_code', 'DAILY_WARD_FEE')
        ->update(['nhis_amount' => 25.00]);

    // Create a cash patient (no insurance)
    $patient = Patient::factory()->create();
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);
    $consultation = Consultation::factory()->create(['patient_checkin_id' => $checkin->id]);
    $ward = Ward::factory()->create();

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
        'admitted_at' => now()->subDay(),
    ]);

    $this->artisan('admissions:generate-daily-charges')
        ->assertSuccessful();

    $charge = Charge::where('patient_checkin_id', $checkin->id)
        ->where('service_code', 'DAILY_WARD_FEE')
        ->first();

    expect($charge)->not->toBeNull();
    expect((float) $charge->amount)->toBe(100.00); // Uses base_amount for cash patients
});
