<?php

/**
 * Property-Based Test for Non-Injectable Prescriptions Following Existing Workflow
 *
 * **Feature: injectable-claim-items, Property 2: Non-injectable prescriptions follow existing workflow**
 * **Validates: Requirements 1.3, 7.1, 7.2**
 *
 * Property: For any prescription created with a non-null quantity for an insured patient,
 * the observer shall create a billing charge and a claim item following the existing logic,
 * with is_pending_quantity = false on the resulting claim item.
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
    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patient->id,
        'patient_insurance_id' => $this->patientInsurance->id,
        'patient_checkin_id' => $this->checkin->id,
    ]);

    // Create a category-level coverage rule for drugs
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_type' => 'percentage',
        'coverage_value' => 100,
        'is_active' => true,
    ]);

    // Authenticate so PharmacyBillingService can set created_by fields on charges
    $this->actingAs(User::factory()->create());
});

/**
 * Dataset: Non-injectable drug forms that always have quantity set at prescription time.
 */
dataset('non_injectable_drug_forms', function () {
    return [
        ['tablet'],
        ['capsule'],
        ['syrup'],
        ['suspension'],
        ['cream'],
        ['drops'],
    ];
});

/**
 * Dataset: Random quantities for property testing.
 */
dataset('random_quantities', function () {
    return array_map(
        fn () => [fake()->numberBetween(1, 100)],
        range(1, 20)
    );
});

it('creates a billing charge for non-injectable prescriptions with quantity', function (string $drugForm, int $quantity) {
    $drug = Drug::factory()->create([
        'form' => $drugForm,
        'unit_price' => fake()->randomFloat(2, 1, 500),
    ]);

    $chargeCountBefore = Charge::count();

    // Create prescription WITH quantity — triggers observer normally
    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '10mg',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'quantity' => $quantity,
        'dosage_form' => $drugForm,
        'instructions' => 'Take as directed',
        'status' => 'prescribed',
        'is_unpriced' => false,
    ]);

    // Assert: A billing charge was created (existing workflow)
    expect(Charge::count())->toBe($chargeCountBefore + 1);

    $charge = Charge::where('prescription_id', $prescription->id)->first();
    expect($charge)->not->toBeNull()
        ->and($charge->service_type)->toBe('pharmacy')
        ->and($charge->service_code)->toBe($drug->drug_code);
})->with('non_injectable_drug_forms', 'random_quantities');

it('creates a claim item with is_pending_quantity false for non-injectable prescriptions', function (string $drugForm, int $quantity) {
    $drug = Drug::factory()->create([
        'form' => $drugForm,
        'unit_price' => fake()->randomFloat(2, 1, 500),
    ]);

    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '10mg',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'quantity' => $quantity,
        'dosage_form' => $drugForm,
        'instructions' => 'Take as directed',
        'status' => 'prescribed',
        'is_unpriced' => false,
    ]);

    // Assert: Claim item created via existing workflow has is_pending_quantity = false
    $claimItem = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
        ->where('code', $drug->drug_code)
        ->first();

    expect($claimItem)->not->toBeNull()
        ->and($claimItem->is_pending_quantity)->toBeFalse()
        ->and((int) $claimItem->quantity)->toBe($quantity);
})->with('non_injectable_drug_forms', 'random_quantities');

it('does not create a pending claim item for non-injectable prescriptions', function (string $drugForm) {
    $drug = Drug::factory()->create([
        'form' => $drugForm,
        'unit_price' => fake()->randomFloat(2, 1, 500),
    ]);

    $quantity = fake()->numberBetween(1, 50);

    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '10mg',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'quantity' => $quantity,
        'dosage_form' => $drugForm,
        'instructions' => 'Take as directed',
        'status' => 'prescribed',
        'is_unpriced' => false,
    ]);

    // Assert: No pending claim items exist — only resolved ones
    $pendingItems = InsuranceClaimItem::where('insurance_claim_id', $this->claim->id)
        ->where('is_pending_quantity', true)
        ->count();

    expect($pendingItems)->toBe(0);
})->with('non_injectable_drug_forms');
