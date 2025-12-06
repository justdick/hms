<?php

/**
 * Property-Based Test for Modal Close Without Save
 *
 * **Feature: nhis-claims-integration, Property 15: Modal Close Without Save**
 * **Validates: Requirements 8.5**
 *
 * Property: For any vetting modal that is closed without clicking "Approve Claim",
 * no changes to the claim record should be persisted.
 */

use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    InsuranceClaimDiagnosis::query()->delete();
    InsuranceClaim::query()->delete();

    Permission::firstOrCreate(['name' => 'insurance.vet-claims']);
    Permission::firstOrCreate(['name' => 'insurance.view-claims']);
    Permission::firstOrCreate(['name' => 'system.admin']);
});

it('does not persist changes when only fetching vetting data', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'gdrg_tariff_id' => null,
        'gdrg_amount' => null,
        'vetted_by' => null,
        'vetted_at' => null,
    ]);

    // Store original claim state
    $originalStatus = $claim->status;
    $originalGdrgTariffId = $claim->gdrg_tariff_id;
    $originalGdrgAmount = $claim->gdrg_amount;
    $originalVettedBy = $claim->vetted_by;
    $originalVettedAt = $claim->vetted_at;

    // Act: Fetch vetting data (simulates opening modal)
    $response = $this->actingAs($user)
        ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data");

    $response->assertOk();

    // Assert: Claim state remains unchanged (modal was just opened, not saved)
    $claim->refresh();

    expect($claim->status)->toBe($originalStatus)
        ->and($claim->gdrg_tariff_id)->toBe($originalGdrgTariffId)
        ->and($claim->gdrg_amount)->toBe($originalGdrgAmount)
        ->and($claim->vetted_by)->toBe($originalVettedBy)
        ->and($claim->vetted_at)->toBe($originalVettedAt);
});

it('does not persist G-DRG selection until approval is submitted', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'gdrg_tariff_id' => null,
        'gdrg_amount' => null,
    ]);

    // Act: Fetch vetting data multiple times (simulates opening/closing modal)
    for ($i = 0; $i < 3; $i++) {
        $response = $this->actingAs($user)
            ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data");

        $response->assertOk();
    }

    // Assert: Claim still has no G-DRG (user never clicked approve)
    $claim->refresh();

    expect($claim->gdrg_tariff_id)->toBeNull()
        ->and($claim->gdrg_amount)->toBeNull()
        ->and($claim->status)->toBe('pending_vetting');
});

it('preserves original claim state when modal is closed without approval', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create claim with specific initial values
    $initialTotalAmount = 500.00;
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'total_claim_amount' => $initialTotalAmount,
        'gdrg_tariff_id' => null,
        'vetted_by' => null,
        'vetted_at' => null,
    ]);

    // Store complete original state
    $originalAttributes = $claim->getAttributes();

    // Act: Open modal (fetch vetting data)
    $response = $this->actingAs($user)
        ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data");

    $response->assertOk();

    // Simulate user closing modal without saving (no POST request made)

    // Assert: All original attributes are preserved
    $claim->refresh();
    $currentAttributes = $claim->getAttributes();

    // Check key fields that should not change
    expect($currentAttributes['status'])->toBe($originalAttributes['status'])
        ->and($currentAttributes['gdrg_tariff_id'])->toBe($originalAttributes['gdrg_tariff_id'])
        ->and($currentAttributes['vetted_by'])->toBe($originalAttributes['vetted_by'])
        ->and($currentAttributes['vetted_at'])->toBe($originalAttributes['vetted_at'])
        ->and((float) $currentAttributes['total_claim_amount'])->toBe((float) $originalAttributes['total_claim_amount']);
});

/**
 * Property test with random claim states
 */
dataset('random_claim_states', function () {
    return [
        'pending_vetting' => ['pending_vetting'],
        'draft' => ['draft'],
    ];
});

it('maintains claim state regardless of how many times vetting data is fetched', function (string $initialStatus) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => $initialStatus,
        'gdrg_tariff_id' => null,
        'vetted_by' => null,
        'vetted_at' => null,
    ]);

    // Store original state
    $originalStatus = $claim->status;

    // Act: Fetch vetting data multiple times (random number between 1-5)
    $fetchCount = rand(1, 5);
    for ($i = 0; $i < $fetchCount; $i++) {
        $this->actingAs($user)
            ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data");
    }

    // Assert: Status remains unchanged
    $claim->refresh();
    expect($claim->status)->toBe($originalStatus)
        ->and($claim->vetted_by)->toBeNull()
        ->and($claim->vetted_at)->toBeNull();
})->with('random_claim_states');

it('only persists changes when approval is explicitly submitted', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 200.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'gdrg_tariff_id' => null,
    ]);

    // Act 1: Fetch vetting data (open modal)
    $this->actingAs($user)
        ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data")
        ->assertOk();

    // Assert 1: No changes yet
    $claim->refresh();
    expect($claim->status)->toBe('pending_vetting')
        ->and($claim->gdrg_tariff_id)->toBeNull();

    // Act 2: Submit approval
    $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'approve',
            'gdrg_tariff_id' => $gdrgTariff->id,
        ]);

    // Assert 2: Now changes are persisted
    $claim->refresh();
    expect($claim->status)->toBe('vetted')
        ->and($claim->gdrg_tariff_id)->toBe($gdrgTariff->id)
        ->and($claim->vetted_by)->toBe($user->id)
        ->and($claim->vetted_at)->not->toBeNull();
});
