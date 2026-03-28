<?php

/**
 * Property-Based Test for Pending Claim Item Creation for Injectable Prescriptions
 *
 * **Feature: injectable-claim-items, Property 1: Pending claim item creation for injectable prescriptions**
 * **Validates: Requirements 1.1, 1.2, 1.4**
 *
 * Property: For any injectable prescription (quantity is null) created for an insured patient
 * with an active insurance claim, the service shall create exactly one claim item on that claim
 * with quantity = 0, insurance_pays = 0, patient_pays = 0, subtotal = 0,
 * is_pending_quantity = true, the drug's drug_code as code, item_type = 'drug',
 * and no associated billing charge.
 */

use App\Models\Consultation;
use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
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
    ]);
    $this->service = app(InsuranceClaimService::class);
});

/**
 * Dataset: Drug forms commonly used for injectable/infusion prescriptions.
 * These are valid enum values from the drugs.form column.
 */
dataset('injectable_drug_forms', function () {
    return [
        ['injection'],
        ['cream'],
        ['drops'],
        ['ointment'],
        ['other'],
    ];
});

/**
 * Dataset: Random drug prices for property testing.
 */
dataset('random_drug_prices', function () {
    return array_map(
        fn () => [fake()->randomFloat(2, 1, 1000)],
        range(1, 20)
    );
});

it('creates a pending claim item with correct zero-amount fields for any injectable prescription', function (string $drugForm, float $drugPrice) {
    // Arrange: Create an injectable drug with random form and price
    $drug = Drug::factory()->create([
        'form' => $drugForm,
        'unit_price' => $drugPrice,
    ]);

    // Create an injectable prescription (quantity is null) without triggering observer
    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    // Act: Call the service method directly
    $item = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    // Assert Property 1: All financial fields are zero, pending flag is true
    expect($item)->toBeInstanceOf(InsuranceClaimItem::class)
        ->and((int) $item->quantity)->toBe(0)
        ->and((float) $item->insurance_pays)->toBe(0.00)
        ->and((float) $item->patient_pays)->toBe(0.00)
        ->and((float) $item->subtotal)->toBe(0.00)
        ->and((float) $item->unit_tariff)->toBe(0.00)
        ->and($item->is_pending_quantity)->toBeTrue()
        ->and($item->item_type)->toBe('drug')
        ->and($item->code)->toBe($drug->drug_code)
        ->and($item->charge_id)->toBeNull()
        ->and($item->insurance_claim_id)->toBe($this->claim->id);
})->with('injectable_drug_forms', 'random_drug_prices');

it('stores the drug name with pending label in description for any injectable drug', function (string $drugForm) {
    $drug = Drug::factory()->create(['form' => $drugForm]);

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $item = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    expect($item->description)->toBe("{$drug->name} (Pending quantity)");
})->with('injectable_drug_forms');

it('sets item_date from the prescription created_at timestamp', function () {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $item = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    expect($item->item_date->toDateString())
        ->toBe($prescription->created_at->toDateString());
});

it('creates exactly one claim item per call regardless of drug price', function (float $drugPrice) {
    $drug = Drug::factory()->injection()->create(['unit_price' => $drugPrice]);

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $itemCountBefore = $this->claim->items()->count();

    $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    expect($this->claim->items()->count())->toBe($itemCountBefore + 1);
})->with('random_drug_prices');

it('persists the pending claim item to the database', function () {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $item = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    // Verify it's persisted by re-fetching from DB
    $freshItem = InsuranceClaimItem::find($item->id);

    expect($freshItem)->not->toBeNull()
        ->and($freshItem->is_pending_quantity)->toBeTrue()
        ->and((int) $freshItem->quantity)->toBe(0)
        ->and((float) $freshItem->subtotal)->toBe(0.00)
        ->and($freshItem->charge_id)->toBeNull();
});
