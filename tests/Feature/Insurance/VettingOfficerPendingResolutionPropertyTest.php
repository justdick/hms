<?php

/**
 * Property-Based Test for Vetting Officer Update Resolves Pending Claim Item Without Charge
 *
 * **Feature: injectable-claim-items, Property 6: Vetting officer update resolves pending claim item without charge**
 * **Validates: Requirements 4.1, 4.2**
 *
 * Property: For any pending claim item (is_pending_quantity = true), when the vetting officer
 * sets a quantity, the claim item shall be updated with the new quantity, recalculated tariffs,
 * is_pending_quantity = false, and no billing charge shall be created.
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
    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    // Create a general coverage rule for pharmacy/drug category so calculateCoverage works
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

    $this->service = app(InsuranceClaimService::class);
});

/**
 * Dataset: Random quantities a vetting officer might enter.
 */
dataset('vetting_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 100)],
        range(1, 10)
    );
});

/**
 * Dataset: Random drug prices for property testing.
 */
dataset('random_drug_prices_p6', function () {
    return array_map(
        fn () => [fake()->randomFloat(2, 1, 500)],
        range(1, 5)
    );
});

it('resolves pending claim item with correct quantity and sets is_pending_quantity to false', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    // Create the pending claim item first
    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);
    expect($pendingItem->is_pending_quantity)->toBeTrue();

    // Act: Vetting officer resolves the pending item
    $resolvedItem = $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Assert: quantity updated, pending flag cleared
    expect($resolvedItem->is_pending_quantity)->toBeFalse()
        ->and((int) $resolvedItem->quantity)->toBe($quantity);
})->with('vetting_quantities');

it('recalculates tariffs when vetting officer sets quantity', function (int $quantity, float $drugPrice) {
    $drug = Drug::factory()->injection()->create(['unit_price' => $drugPrice]);

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    // Verify initial zero state
    expect((float) $pendingItem->subtotal)->toBe(0.00)
        ->and((float) $pendingItem->unit_tariff)->toBe(0.00)
        ->and((float) $pendingItem->insurance_pays)->toBe(0.00)
        ->and((float) $pendingItem->patient_pays)->toBe(0.00);

    // Act: Vetting officer resolves
    $resolvedItem = $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Assert: financial fields are recalculated (no longer all zeros for qty > 0)
    // The exact values depend on coverage rules, but subtotal should reflect quantity * tariff
    expect((float) $resolvedItem->subtotal)->toBe(round((float) $resolvedItem->unit_tariff * $quantity, 2))
        ->and((float) $resolvedItem->insurance_pays + (float) $resolvedItem->patient_pays)
        ->toBe((float) $resolvedItem->subtotal);
})->with('vetting_quantities', 'random_drug_prices_p6');

it('does not create any billing charge when vetting officer resolves pending item', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    $chargeCountBefore = Charge::count();

    // Act: Vetting officer resolves
    $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Assert: No new charges created (Requirement 4.2)
    expect(Charge::count())->toBe($chargeCountBefore);
})->with('vetting_quantities');

it('keeps charge_id null after vetting officer resolution', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    // Act
    $resolvedItem = $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Assert: charge_id remains null — vetting officer doesn't create charges
    expect($resolvedItem->charge_id)->toBeNull();
})->with('vetting_quantities');

it('removes the "(Pending quantity)" label from description after resolution', function () {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);
    expect($pendingItem->description)->toContain('(Pending quantity)');

    // Act
    $resolvedItem = $this->service->updatePendingClaimItemQuantity($pendingItem, 5);

    // Assert: pending label removed
    expect($resolvedItem->description)->not->toContain('(Pending quantity)')
        ->and($resolvedItem->description)->toBe($drug->name);
});

it('recalculates parent claim totals after vetting officer resolution', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);

    // Act
    $resolvedItem = $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Assert: claim totals reflect the resolved item
    $freshClaim = $this->claim->fresh();
    $itemsSum = $freshClaim->items->sum('subtotal');
    $insuranceSum = $freshClaim->items->sum('insurance_pays');
    $patientSum = $freshClaim->items->sum('patient_pays');

    expect((float) $freshClaim->total_claim_amount)->toBe(round((float) $itemsSum, 2))
        ->and((float) $freshClaim->insurance_covered_amount)->toBe(round((float) $insuranceSum, 2))
        ->and((float) $freshClaim->patient_copay_amount)->toBe(round((float) $patientSum, 2));
})->with('vetting_quantities');

it('persists resolved state to database after vetting officer update', function () {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);
    $quantity = fake()->numberBetween(1, 50);

    // Act
    $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Assert: re-fetch from DB to confirm persistence
    $freshItem = InsuranceClaimItem::find($pendingItem->id);

    expect($freshItem->is_pending_quantity)->toBeFalse()
        ->and((int) $freshItem->quantity)->toBe($quantity)
        ->and($freshItem->charge_id)->toBeNull()
        ->and($freshItem->description)->not->toContain('(Pending quantity)');
});
