<?php

use App\Models\Charge;
use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\Prescription;
use App\Services\InsuranceClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->provider = InsuranceProvider::factory()->create(['name' => 'NHIS']);
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'NHIS Standard',
        'plan_code' => 'NHIS-STD',
        'is_active' => true,
    ]);
    $this->patient = Patient::factory()->create();
    $this->patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $this->patient->id,
        'insurance_plan_id' => $this->plan->id,
    ]);
    $this->checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
    ]);
});

it('uses actual quantity for regular drugs in NHIS claims', function () {
    $user = \App\Models\User::factory()->create();

    // Create a regular drug (nhis_claim_qty_as_one = false)
    $drug = Drug::factory()->create([
        'nhis_claim_qty_as_one' => false,
    ]);

    // Create prescription without triggering observer
    $consultation = \App\Models\Consultation::factory()->create();
    $prescription = Prescription::withoutEvents(function () use ($consultation, $drug) {
        return Prescription::factory()->create([
            'consultation_id' => $consultation->id,
            'drug_id' => $drug->id,
            'quantity' => 6,
        ]);
    });

    // Create charge directly
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'charge_type' => 'medication',
        'metadata' => ['quantity' => 6],
        'created_by_type' => \App\Models\User::class,
        'created_by_id' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    $service = app(InsuranceClaimService::class);
    $service->addChargesToClaim($claim, [$charge->id]);

    $claimItem = $claim->items()->first();
    expect($claimItem->quantity)->toBe(6);
});

it('uses quantity 1 for drugs with nhis_claim_qty_as_one flag', function () {
    $user = \App\Models\User::factory()->create();

    // Create a drug like Arthemeter/Pessary (nhis_claim_qty_as_one = true)
    $drug = Drug::factory()->create([
        'name' => 'Arthemeter Injection',
        'nhis_claim_qty_as_one' => true,
    ]);

    // Create prescription without triggering observer
    $consultation = \App\Models\Consultation::factory()->create();
    $prescription = Prescription::withoutEvents(function () use ($consultation, $drug) {
        return Prescription::factory()->create([
            'consultation_id' => $consultation->id,
            'drug_id' => $drug->id,
            'quantity' => 6, // Patient receives 6 tablets
        ]);
    });

    // Create charge directly
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'charge_type' => 'medication',
        'metadata' => ['quantity' => 6],
        'created_by_type' => \App\Models\User::class,
        'created_by_id' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    $service = app(InsuranceClaimService::class);
    $service->addChargesToClaim($claim, [$charge->id]);

    $claimItem = $claim->items()->first();
    // For NHIS, quantity should be 1 (counted as 1 pack)
    expect($claimItem->quantity)->toBe(1);
});

it('uses actual quantity for non-pharmacy charges regardless of drug flag', function () {
    // Lab charges should always use actual quantity
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'service_type' => 'lab',
        'metadata' => ['quantity' => 3],
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    $service = app(InsuranceClaimService::class);
    $service->addChargesToClaim($claim, [$charge->id]);

    $claimItem = $claim->items()->first();
    expect($claimItem->quantity)->toBe(3);
});
