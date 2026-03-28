<?php

/**
 * Unit tests for PrescriptionObserver edge cases related to injectable prescriptions.
 *
 * **Feature: injectable-claim-items**
 * **Validates: Requirements 1.3, 1.5, 7.1, 7.2**
 *
 * Tests that the observer correctly skips pending claim item creation
 * when preconditions are not met.
 */

use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the insurance_admin role so DrugObserver notifications don't fail
    \Spatie\Permission\Models\Role::firstOrCreate([
        'name' => 'insurance_admin',
        'guard_name' => 'web',
    ]);

    $this->provider = InsuranceProvider::factory()->create(['name' => 'NHIS']);
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
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
    $this->consultation = Consultation::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
    ]);
});

it('skips pending claim item creation when no insurance claim exists for the check-in', function () {
    // No InsuranceClaim created for this checkin
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '500mg',
        'frequency' => 'Once daily',
        'duration' => '3 days',
        'quantity' => null,
        'dosage_form' => 'injection',
        'instructions' => 'Administer IV',
        'status' => 'prescribed',
        'is_unpriced' => false,
    ]);

    // Assert: No claim items created, no errors thrown
    expect(InsuranceClaimItem::count())->toBe(0);
    expect(Charge::where('prescription_id', $prescription->id)->count())->toBe(0);
});

it('skips pending claim item creation for prescriptions with non-null quantity', function () {
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    // Create coverage rule so the charge/claim flow works
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_type' => 'percentage',
        'coverage_value' => 100,
        'is_active' => true,
    ]);

    $drug = Drug::factory()->tablet()->create(['unit_price' => 10.00]);

    // Authenticate so PharmacyBillingService can set created_by fields
    $this->actingAs(User::factory()->create());

    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '500mg',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'quantity' => 10,
        'dosage_form' => 'tablet',
        'instructions' => 'Take after meals',
        'status' => 'prescribed',
        'is_unpriced' => false,
    ]);

    // Assert: A charge was created (existing workflow), NOT a pending claim item
    expect(Charge::where('prescription_id', $prescription->id)->count())->toBe(1);

    $pendingItems = InsuranceClaimItem::where('insurance_claim_id', $claim->id)
        ->where('is_pending_quantity', true)
        ->count();

    expect($pendingItems)->toBe(0);
});

it('skips pending claim item creation when drug has empty drug_code', function () {
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    $drug = Drug::factory()->injection()->create();

    // Create prescription with null quantity (injectable)
    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
            'is_unpriced' => false,
        ]);
    });

    // Update drug_code to empty string in DB to simulate a drug without a code
    Drug::withoutEvents(function () use ($drug) {
        $drug->newQuery()->where('id', $drug->id)->update(['drug_code' => '']);
    });

    // Clear the cached relationship so the observer reloads the drug from DB
    $prescription->unsetRelation('drug');

    $itemCountBefore = InsuranceClaimItem::count();

    $observer = app(\App\Observers\PrescriptionObserver::class);
    $method = new ReflectionMethod($observer, 'createPendingQuantityClaimItem');
    $method->invoke($observer, $prescription);

    // Assert: No new claim items created because drug_code is empty
    expect(InsuranceClaimItem::count())->toBe($itemCountBefore);
    expect(Charge::where('prescription_id', $prescription->id)->count())->toBe(0);
});

it('creates a pending claim item when all conditions are met for injectable prescription', function () {
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '500mg',
        'frequency' => 'Once daily',
        'duration' => '3 days',
        'quantity' => null,
        'dosage_form' => 'injection',
        'instructions' => 'Administer IV',
        'status' => 'prescribed',
        'is_unpriced' => false,
    ]);

    // Assert: Pending claim item created, no charge
    $pendingItem = InsuranceClaimItem::where('insurance_claim_id', $claim->id)
        ->where('is_pending_quantity', true)
        ->first();

    expect($pendingItem)->not->toBeNull()
        ->and($pendingItem->code)->toBe($drug->drug_code)
        ->and((int) $pendingItem->quantity)->toBe(0)
        ->and((float) $pendingItem->subtotal)->toBe(0.00)
        ->and($pendingItem->charge_id)->toBeNull();

    expect(Charge::where('prescription_id', $prescription->id)->count())->toBe(0);
});
