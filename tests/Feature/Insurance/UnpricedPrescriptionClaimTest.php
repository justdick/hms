<?php

declare(strict_types=1);

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\Prescription;
use App\Services\InsuranceClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create NHIS provider and plan
    $this->nhisProvider = InsuranceProvider::factory()->create([
        'name' => 'NHIS',
        'is_nhis' => true,
    ]);

    $this->nhisPlan = InsurancePlan::factory()->for($this->nhisProvider, 'provider')->create([
        'plan_name' => 'NHIS Standard',
    ]);

    // Create patient with NHIS insurance
    $this->patient = Patient::factory()->create();
    $this->patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $this->patient->id,
        'insurance_plan_id' => $this->nhisPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subYear(),
        'coverage_end_date' => now()->addYear(),
    ]);

    // Create checkin and consultation
    $department = Department::factory()->create();
    $this->checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $department->id,
    ]);

    $this->consultation = Consultation::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
    ]);

    // Create insurance claim for this checkin
    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
        'status' => 'pending_vetting',
    ]);
});

describe('Unpriced prescriptions in claims', function () {
    it('creates a claim item for an unpriced drug when prescription is created via observer', function () {
        // Create an unpriced drug with NHIS tariff
        $drug = Drug::factory()->create([
            'name' => 'Ciprofloxacin 250mg',
            'drug_code' => 'CIPRO250',
            'unit_price' => 0.00,
        ]);

        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'CIPRO250',
            'name' => 'Ciprofloxacin 250mg',
            'category' => 'medicine',
            'price' => 0.61,
            'is_active' => true,
        ]);

        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        // Create prescription — observer should flag as unpriced but still add to claim
        $prescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'medication_name' => $drug->name,
            'quantity' => 20,
            'quantity_to_dispense' => 20,
            'dose_quantity' => '250mg',
            'frequency' => 'Twice daily',
            'duration' => '10 days',
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        // Prescription should be flagged as unpriced
        expect($prescription->is_unpriced)->toBeTrue();

        // No charge should be created (copay is zero for unpriced)
        expect($prescription->charge)->toBeNull();

        // But a claim item SHOULD exist
        $claimItem = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
            ->where('code', 'CIPRO250')
            ->where('item_type', 'drug')
            ->first();

        expect($claimItem)->not->toBeNull()
            ->and($claimItem->quantity)->toBe(20)
            ->and($claimItem->charge_id)->toBeNull()
            ->and((float) $claimItem->unit_tariff)->toBe(0.61)
            ->and((float) $claimItem->subtotal)->toBe(12.20);
    });

    it('does not create claim item for unpriced drug when patient has no insurance claim', function () {
        // Create a checkin without an insurance claim
        $checkinNoInsurance = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
        ]);
        $consultationNoInsurance = Consultation::factory()->create([
            'patient_checkin_id' => $checkinNoInsurance->id,
        ]);

        $drug = Drug::factory()->create([
            'unit_price' => 0.00,
        ]);

        // Create prescription — no claim exists so no claim item should be created
        $prescription = Prescription::create([
            'consultation_id' => $consultationNoInsurance->id,
            'drug_id' => $drug->id,
            'medication_name' => $drug->name,
            'quantity' => 10,
            'quantity_to_dispense' => 10,
            'dose_quantity' => '500mg',
            'frequency' => 'Once daily',
            'duration' => '5 days',
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        expect($prescription->is_unpriced)->toBeTrue();

        // No claim items should be created
        $claimItems = InsuranceClaimItem::where('code', $drug->drug_code)
            ->where('item_type', 'drug')
            ->count();

        expect($claimItems)->toBe(0);
    });

    it('includes unpriced prescriptions when autoLinkCharges is called', function () {
        $drug = Drug::factory()->create([
            'name' => 'Test Unpriced Drug',
            'drug_code' => 'TESTUNP1',
            'unit_price' => 0.00,
        ]);

        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'TESTUNP1',
            'name' => 'Test Unpriced Drug',
            'category' => 'medicine',
            'price' => 2.50,
            'is_active' => true,
        ]);

        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        // Create prescription without observer (simulate existing data)
        $prescription = Prescription::withoutEvents(function () use ($drug) {
            return Prescription::create([
                'consultation_id' => $this->consultation->id,
                'drug_id' => $drug->id,
                'medication_name' => $drug->name,
                'quantity' => 10,
                'quantity_to_dispense' => 10,
                'dose_quantity' => '500mg',
                'frequency' => 'Once daily',
                'duration' => '5 days',
                'dosage_form' => 'tablet',
                'status' => 'prescribed',
                'is_unpriced' => true,
            ]);
        });

        // Call autoLinkCharges — should also pick up unpriced prescriptions
        $claimService = app(InsuranceClaimService::class);
        $claimService->autoLinkCharges($this->claim);

        $claimItem = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
            ->where('code', 'TESTUNP1')
            ->where('item_type', 'drug')
            ->first();

        expect($claimItem)->not->toBeNull()
            ->and($claimItem->charge_id)->toBeNull()
            ->and($claimItem->quantity)->toBe(10)
            ->and((float) $claimItem->unit_tariff)->toBe(2.50);
    });

    it('does not duplicate claim items for unpriced prescriptions', function () {
        $drug = Drug::factory()->create([
            'name' => 'Duplicate Test Drug',
            'drug_code' => 'DUPTEST1',
            'unit_price' => 0.00,
        ]);

        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'DUPTEST1',
            'name' => 'Duplicate Test Drug',
            'category' => 'medicine',
            'price' => 1.00,
            'is_active' => true,
        ]);

        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        // Create prescription via observer (creates claim item)
        Prescription::create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'medication_name' => $drug->name,
            'quantity' => 5,
            'quantity_to_dispense' => 5,
            'dose_quantity' => '100mg',
            'frequency' => 'Once daily',
            'duration' => '5 days',
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        // Call autoLinkCharges again — should NOT duplicate
        $claimService = app(InsuranceClaimService::class);
        $claimService->autoLinkCharges($this->claim);

        $count = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
            ->where('code', 'DUPTEST1')
            ->count();

        expect($count)->toBe(1);
    });

    it('still creates charges for priced drugs normally', function () {
        $drug = Drug::factory()->create([
            'name' => 'Priced Drug',
            'drug_code' => 'PRICED1',
            'unit_price' => 5.00,
        ]);

        // Need an authenticated user for charge creation
        $doctor = \App\Models\User::factory()->create();
        $this->actingAs($doctor);

        // Create prescription — observer should create charge as normal
        $prescription = Prescription::create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'medication_name' => $drug->name,
            'quantity' => 10,
            'quantity_to_dispense' => 10,
            'dose_quantity' => '500mg',
            'frequency' => 'Once daily',
            'duration' => '5 days',
            'dosage_form' => 'tablet',
            'status' => 'prescribed',
        ]);

        expect($prescription->is_unpriced)->toBeFalse();

        // Charge should be created
        $prescription->refresh();
        $charge = $prescription->charge;
        expect($charge)->not->toBeNull()
            ->and((float) $charge->amount)->toBe(50.00);
    });
});
