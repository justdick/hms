<?php

/**
 * Property-Based Test for Pharmacist Review Resolves Pending Claim Item
 *
 * **Feature: injectable-claim-items, Property 3: Pharmacist review resolves pending claim item**
 * **Validates: Requirements 3.1, 3.3**
 *
 * Property: For any injectable prescription with a pending claim item (is_pending_quantity = true),
 * when the pharmacist reviews it with action "keep" and enters a quantity, the claim item shall be
 * updated with the entered quantity, recalculated tariffs (unit_tariff, subtotal, insurance_pays,
 * patient_pays), a linked charge_id, and is_pending_quantity = false.
 */

use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\Prescription;
use App\Services\InsuranceClaimService;
use App\Services\PharmacyBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
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
    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
        'total_claim_amount' => 0,
        'insurance_covered_amount' => 0,
        'patient_copay_amount' => 0,
    ]);

    InsuranceCoverageRule::withoutEvents(function () {
        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $this->plan->id,
            'coverage_category' => 'drug',
            'item_code' => null,
            'is_covered' => true,
            'coverage_type' => 'full',
            'coverage_value' => 100,
            'is_active' => true,
            'effective_from' => null,
            'effective_to' => null,
        ]);
    });

    $this->claimService = app(InsuranceClaimService::class);
    $this->billingService = app(PharmacyBillingService::class);
});

/**
 * Dataset: Random quantities the pharmacist might enter.
 */
dataset('pharmacist_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 50)],
        range(1, 20)
    );
});

/**
 * Dataset: Injectable drug forms.
 */
dataset('injectable_forms', function () {
    return [
        ['injection'],
        ['cream'],
        ['drops'],
        ['ointment'],
        ['other'],
    ];
});

it('resolves a pending claim item when pharmacist creates a charge for the same drug', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    // Step 1: Doctor creates injectable prescription → pending claim item created
    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

    expect($pendingItem->is_pending_quantity)->toBeTrue()
        ->and((int) $pendingItem->quantity)->toBe(0)
        ->and($pendingItem->charge_id)->toBeNull();

    // Step 2: Pharmacist reviews — update prescription quantity and create a charge
    $prescription->update(['quantity' => $quantity, 'quantity_to_dispense' => $quantity]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * $quantity,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    // Step 3: linkChargeToInsuranceClaim detects the pending item and resolves it
    // Use reflection to call the protected method
    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    // Assert: pending item is resolved
    $resolvedItem = $pendingItem->fresh();

    expect($resolvedItem->is_pending_quantity)->toBeFalse()
        ->and((int) $resolvedItem->quantity)->toBe($quantity)
        ->and($resolvedItem->charge_id)->toBe($charge->id)
        ->and((float) $resolvedItem->subtotal)->toBeGreaterThanOrEqual(0)
        ->and((float) $resolvedItem->unit_tariff)->toBeGreaterThanOrEqual(0);
})->with('pharmacist_quantities');

it('links charge_id to the resolved claim item for any injectable drug form', function (string $drugForm, int $quantity) {
    $drug = Drug::factory()->create(['form' => $drugForm]);

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

    $prescription->update(['quantity' => $quantity, 'quantity_to_dispense' => $quantity]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * $quantity,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    $resolvedItem = $pendingItem->fresh();

    expect($resolvedItem->charge_id)->toBe($charge->id)
        ->and($resolvedItem->is_pending_quantity)->toBeFalse();
})->with('injectable_forms', 'pharmacist_quantities');

it('sets is_pending_quantity to false after pharmacist resolution regardless of quantity', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

    $prescription->update(['quantity' => $quantity, 'quantity_to_dispense' => $quantity]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * $quantity,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    $resolvedItem = $pendingItem->fresh();

    expect($resolvedItem->is_pending_quantity)->toBeFalse()
        ->and((int) $resolvedItem->quantity)->toBeGreaterThan(0);
})->with('pharmacist_quantities');

it('recalculates claim totals after pharmacist resolves a pending item', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

    $prescription->update(['quantity' => $quantity, 'quantity_to_dispense' => $quantity]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * $quantity,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    $freshClaim = $this->claim->fresh();
    $freshClaim->load('items');

    $expectedTotal = round((float) $freshClaim->items->sum('subtotal'), 2);
    $expectedInsurance = round((float) $freshClaim->items->sum('insurance_pays'), 2);
    $expectedPatient = round((float) $freshClaim->items->sum('patient_pays'), 2);

    expect((float) $freshClaim->total_claim_amount)->toBe($expectedTotal)
        ->and((float) $freshClaim->insurance_covered_amount)->toBe($expectedInsurance)
        ->and((float) $freshClaim->patient_copay_amount)->toBe($expectedPatient);
})->with('pharmacist_quantities');
