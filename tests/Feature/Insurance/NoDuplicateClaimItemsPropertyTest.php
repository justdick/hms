<?php

/**
 * Property-Based Test for No Duplicate Claim Items After Pharmacist Review
 *
 * **Feature: injectable-claim-items, Property 5: No duplicate claim items after pharmacist review**
 * **Validates: Requirements 3.4**
 *
 * Property: For any injectable prescription with an existing pending claim item on a claim,
 * after the pharmacist reviews it with action "keep", the claim shall contain exactly one
 * claim item for that drug code — not two.
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
 * Dataset: Random quantities for pharmacist dispensing.
 */
dataset('dispense_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 50)],
        range(1, 20)
    );
});

it('produces exactly one claim item per drug code after pharmacist resolves a pending item', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    // Create pending claim item
    $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

    // Pharmacist reviews and creates charge
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

    // Exactly one claim item for this drug code
    $itemsForDrug = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
        ->where('code', $drug->drug_code)
        ->where('item_type', 'drug')
        ->count();

    expect($itemsForDrug)->toBe(1);
})->with('dispense_quantities');

it('produces exactly one claim item per drug code when vetting officer resolved first', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    // Create pending claim item, then vetting officer resolves
    $pendingItem = $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);
    $this->claimService->updatePendingClaimItemQuantity($pendingItem, fake()->numberBetween(1, 20));

    // Pharmacist subsequently creates charge
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

    $itemsForDrug = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
        ->where('code', $drug->drug_code)
        ->where('item_type', 'drug')
        ->count();

    expect($itemsForDrug)->toBe(1);
})->with('dispense_quantities');

it('maintains one item per drug even with multiple injectable prescriptions for different drugs', function () {
    $drugCount = fake()->numberBetween(2, 5);
    $drugs = [];

    for ($i = 0; $i < $drugCount; $i++) {
        $drug = Drug::factory()->injection()->create();
        $drugs[] = $drug;

        $prescription = Prescription::withoutEvents(function () use ($drug) {
            return Prescription::factory()->create([
                'consultation_id' => $this->consultation->id,
                'drug_id' => $drug->id,
                'quantity' => null,
                'status' => 'prescribed',
            ]);
        });

        // Create pending claim item for each
        $this->claimService->createPendingQuantityClaimItem($this->claim, $prescription);

        // Pharmacist resolves each
        $qty = fake()->numberBetween(1, 30);
        $prescription->update(['quantity' => $qty, 'quantity_to_dispense' => $qty]);

        $charge = Charge::factory()->create([
            'patient_checkin_id' => $this->checkin->id,
            'prescription_id' => $prescription->id,
            'service_type' => 'pharmacy',
            'service_code' => $drug->drug_code,
            'amount' => $drug->unit_price * $qty,
            'charge_type' => 'medication',
            'status' => 'pending',
        ]);

        $method = new ReflectionMethod($this->billingService, 'linkChargeToInsuranceClaim');
        $method->invoke($this->billingService, $charge, $this->checkin->id);
    }

    // Total claim items should equal number of drugs (one per drug)
    expect($this->claim->items()->count())->toBe($drugCount);

    // Each drug code should appear exactly once
    foreach ($drugs as $drug) {
        $count = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
            ->where('code', $drug->drug_code)
            ->count();

        expect($count)->toBe(1);
    }
});

it('falls through to standard logic when no pending item exists for the drug', function () {
    $drug = Drug::factory()->create(['form' => 'tablet']);

    // Create a regular prescription (not injectable, has quantity)
    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => 10,
            'status' => 'prescribed',
        ]);
    });

    // No pending claim item exists for this drug

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

    // A new claim item should be created via addChargesToClaim
    $itemsForDrug = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
        ->where('code', $drug->drug_code)
        ->count();

    expect($itemsForDrug)->toBe(1);
});
