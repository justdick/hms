<?php

/**
 * Property-Based Test for Vetting Officer Priority — Charge Links Without Financial Overwrite
 *
 * **Feature: injectable-claim-items, Property 4: Vetting officer priority — charge links without financial overwrite**
 * **Validates: Requirements 3.2, 5.1**
 *
 * Property: For any injectable prescription whose claim item has already been resolved by the
 * vetting officer (is_pending_quantity = false, charge_id = null), when the pharmacist subsequently
 * reviews and creates a charge, the charge_id shall be linked to the claim item without modifying
 * the quantity, unit_tariff, subtotal, insurance_pays, or patient_pays.
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
 * Dataset: Vetting officer quantities (what the vetting officer sets).
 */
dataset('vetting_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 30)],
        range(1, 10)
    );
});

/**
 * Dataset: Pharmacist quantities (what the pharmacist dispenses — may differ from vetting officer).
 */
dataset('pharmacist_dispense_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 50)],
        range(1, 10)
    );
});

it('preserves vetting officer financial amounts when pharmacist charge is linked', function (int $vettingQty, int $pharmacistQty) {
    $drug = Drug::factory()->injection()->create();

    // Step 1: Doctor creates injectable prescription → pending claim item
    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

    // Step 2: Vetting officer resolves the pending item first
    $this->claimService->updatePendingClaimItemQuantity($pendingItem, $vettingQty);
    $vettedItem = $pendingItem->fresh();

    // Capture the vetting officer's financial values
    $vettedQuantity = (int) $vettedItem->quantity;
    $vettedUnitTariff = (float) $vettedItem->unit_tariff;
    $vettedSubtotal = (float) $vettedItem->subtotal;
    $vettedInsurancePays = (float) $vettedItem->insurance_pays;
    $vettedPatientPays = (float) $vettedItem->patient_pays;

    expect($vettedItem->is_pending_quantity)->toBeFalse()
        ->and($vettedItem->charge_id)->toBeNull();

    // Step 3: Pharmacist subsequently creates a charge (possibly different quantity)
    $prescription->update(['quantity' => $pharmacistQty, 'quantity_to_dispense' => $pharmacistQty]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * $pharmacistQty,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    // Step 4: linkChargeToInsuranceClaim should only link charge_id, not overwrite financials
    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    $finalItem = $pendingItem->fresh();

    // Assert: charge_id is linked
    expect($finalItem->charge_id)->toBe($charge->id)
        // Assert: financial amounts are preserved from vetting officer
        ->and((int) $finalItem->quantity)->toBe($vettedQuantity)
        ->and((float) $finalItem->unit_tariff)->toBe($vettedUnitTariff)
        ->and((float) $finalItem->subtotal)->toBe($vettedSubtotal)
        ->and((float) $finalItem->insurance_pays)->toBe($vettedInsurancePays)
        ->and((float) $finalItem->patient_pays)->toBe($vettedPatientPays)
        // Assert: still resolved (not re-pending)
        ->and($finalItem->is_pending_quantity)->toBeFalse();
})->with('vetting_quantities', 'pharmacist_dispense_quantities');

it('does not create a duplicate claim item when vetting officer already resolved', function (int $vettingQty) {
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
    $this->claimService->updatePendingClaimItemQuantity($pendingItem, $vettingQty);

    $itemCountBefore = $this->claim->items()->count();

    // Pharmacist creates charge
    $pharmacistQty = fake()->numberBetween(1, 50);
    $prescription->update(['quantity' => $pharmacistQty, 'quantity_to_dispense' => $pharmacistQty]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * $pharmacistQty,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    // No new claim items should be created
    expect($this->claim->items()->count())->toBe($itemCountBefore);
})->with('vetting_quantities');

it('links charge_id even when vetting officer and pharmacist quantities differ', function () {
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

    // Vetting officer sets qty = 5
    $this->claimService->updatePendingClaimItemQuantity($pendingItem, 5);

    // Pharmacist dispenses qty = 10 (different)
    $prescription->update(['quantity' => 10, 'quantity_to_dispense' => 10]);

    $charge = Charge::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'prescription_id' => $prescription->id,
        'service_type' => 'pharmacy',
        'service_code' => $drug->drug_code,
        'amount' => $drug->unit_price * 10,
        'charge_type' => 'medication',
        'status' => 'pending',
    ]);

    $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
    $method->invoke($this->billingService, $charge, $this->checkin->id);

    $finalItem = $pendingItem->fresh();

    // Vetting officer's quantity (5) is preserved, not pharmacist's (10)
    expect($finalItem->charge_id)->toBe($charge->id)
        ->and((int) $finalItem->quantity)->toBe(5)
        ->and($finalItem->is_pending_quantity)->toBeFalse();
});
