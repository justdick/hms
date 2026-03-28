<?php

/**
 * Property-Based Test for Claim Totals Equal Sum of Item Amounts
 *
 * **Feature: injectable-claim-items, Property 7: Claim totals equal sum of item amounts**
 * **Validates: Requirements 4.3**
 *
 * Property: For any insurance claim, after any claim item update (including pending quantity
 * resolution), the claim's total_claim_amount shall equal the sum of all item subtotal values,
 * insurance_covered_amount shall equal the sum of all item insurance_pays values, and
 * patient_copay_amount shall equal the sum of all item patient_pays values.
 */

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
        'total_claim_amount' => 0,
        'insurance_covered_amount' => 0,
        'patient_copay_amount' => 0,
    ]);

    // Create a general coverage rule for drug category so calculateCoverage works
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
 * Dataset: Random quantities for pending item resolution.
 */
dataset('resolution_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 50)],
        range(1, 10)
    );
});

/**
 * Dataset: Random counts of pre-existing claim items on a claim.
 */
dataset('existing_item_counts', function () {
    return [[0], [1], [2], [3], [5]];
});

it('has claim totals equal to sum of item amounts after resolving a single pending item', function (int $quantity) {
    $drug = Drug::factory()->injection()->create();

    $prescription = Prescription::withoutEvents(function () use ($drug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $drug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });

    // Create pending item and resolve it
    $pendingItem = $this->service->createPendingQuantityClaimItem($this->claim, $prescription);
    $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Re-fetch claim with items
    $freshClaim = $this->claim->fresh();
    $freshClaim->load('items');

    $expectedTotal = round((float) $freshClaim->items->sum('subtotal'), 2);
    $expectedInsurance = round((float) $freshClaim->items->sum('insurance_pays'), 2);
    $expectedPatient = round((float) $freshClaim->items->sum('patient_pays'), 2);

    expect((float) $freshClaim->total_claim_amount)->toBe($expectedTotal)
        ->and((float) $freshClaim->insurance_covered_amount)->toBe($expectedInsurance)
        ->and((float) $freshClaim->patient_copay_amount)->toBe($expectedPatient);
})->with('resolution_quantities');

it('has claim totals equal to sum of item amounts with pre-existing items plus a resolved pending item', function (int $existingCount, int $quantity) {
    // Create pre-existing resolved claim items with known amounts
    for ($i = 0; $i < $existingCount; $i++) {
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $this->claim->id,
            'item_type' => fake()->randomElement(['consultation', 'lab', 'procedure']),
            'is_pending_quantity' => false,
        ]);
    }

    // Recalculate totals to account for pre-existing items
    $this->service->recalculateClaimTotals($this->claim);

    // Now create and resolve a pending injectable item
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
    $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Re-fetch and verify totals
    $freshClaim = $this->claim->fresh();
    $freshClaim->load('items');

    $expectedTotal = round((float) $freshClaim->items->sum('subtotal'), 2);
    $expectedInsurance = round((float) $freshClaim->items->sum('insurance_pays'), 2);
    $expectedPatient = round((float) $freshClaim->items->sum('patient_pays'), 2);

    expect((float) $freshClaim->total_claim_amount)->toBe($expectedTotal)
        ->and((float) $freshClaim->insurance_covered_amount)->toBe($expectedInsurance)
        ->and((float) $freshClaim->patient_copay_amount)->toBe($expectedPatient);
})->with('existing_item_counts', 'resolution_quantities');

it('has claim totals equal to sum of item amounts after resolving multiple pending items sequentially', function () {
    $drugCount = fake()->numberBetween(2, 5);
    $resolvedItems = [];

    for ($i = 0; $i < $drugCount; $i++) {
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
        $qty = fake()->numberBetween(1, 30);
        $this->service->updatePendingClaimItemQuantity($pendingItem, $qty);
        $resolvedItems[] = $pendingItem->fresh();
    }

    // Verify totals after all resolutions
    $freshClaim = $this->claim->fresh();
    $freshClaim->load('items');

    $expectedTotal = round((float) $freshClaim->items->sum('subtotal'), 2);
    $expectedInsurance = round((float) $freshClaim->items->sum('insurance_pays'), 2);
    $expectedPatient = round((float) $freshClaim->items->sum('patient_pays'), 2);

    expect((float) $freshClaim->total_claim_amount)->toBe($expectedTotal)
        ->and((float) $freshClaim->insurance_covered_amount)->toBe($expectedInsurance)
        ->and((float) $freshClaim->patient_copay_amount)->toBe($expectedPatient)
        ->and($freshClaim->items->count())->toBe($drugCount);
});

it('has claim totals include unresolved pending items as zero amounts', function (int $quantity) {
    // Create one pending item that stays unresolved
    $unresolvedDrug = Drug::factory()->injection()->create();
    $unresolvedPrescription = Prescription::withoutEvents(function () use ($unresolvedDrug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $unresolvedDrug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });
    $this->service->createPendingQuantityClaimItem($this->claim, $unresolvedPrescription);

    // Create and resolve another pending item
    $resolvedDrug = Drug::factory()->injection()->create();
    $resolvedPrescription = Prescription::withoutEvents(function () use ($resolvedDrug) {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $resolvedDrug->id,
            'quantity' => null,
            'status' => 'prescribed',
        ]);
    });
    $resolvedItem = $this->service->createPendingQuantityClaimItem($this->claim, $resolvedPrescription);
    $this->service->updatePendingClaimItemQuantity($resolvedItem, $quantity);

    // Verify totals still equal sum of ALL items (including the zero-amount pending one)
    $freshClaim = $this->claim->fresh();
    $freshClaim->load('items');

    $expectedTotal = round((float) $freshClaim->items->sum('subtotal'), 2);
    $expectedInsurance = round((float) $freshClaim->items->sum('insurance_pays'), 2);
    $expectedPatient = round((float) $freshClaim->items->sum('patient_pays'), 2);

    expect((float) $freshClaim->total_claim_amount)->toBe($expectedTotal)
        ->and((float) $freshClaim->insurance_covered_amount)->toBe($expectedInsurance)
        ->and((float) $freshClaim->patient_copay_amount)->toBe($expectedPatient)
        ->and($freshClaim->items->count())->toBe(2);
})->with('resolution_quantities');

it('maintains total invariant: insurance_pays + patient_pays = subtotal for each item after resolution', function (int $quantity) {
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
    $resolvedItem = $this->service->updatePendingClaimItemQuantity($pendingItem, $quantity);

    // Per-item invariant: insurance_pays + patient_pays = subtotal
    expect(round((float) $resolvedItem->insurance_pays + (float) $resolvedItem->patient_pays, 2))
        ->toBe(round((float) $resolvedItem->subtotal, 2));

    // Claim-level invariant: totals equal sums
    $freshClaim = $this->claim->fresh();
    expect(round((float) $freshClaim->insurance_covered_amount + (float) $freshClaim->patient_copay_amount, 2))
        ->toBe(round((float) $freshClaim->total_claim_amount, 2));
})->with('resolution_quantities');
