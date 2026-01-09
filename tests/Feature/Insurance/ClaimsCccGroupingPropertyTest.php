<?php

/**
 * Property-Based Test for Claims CCC Grouping
 *
 * **Feature: multi-department-checkin, Property 5: Claims CCC Grouping**
 * **Validates: Requirements 4.1, 4.2**
 *
 * Property: For any set of claims with the same CCC, when the claims list is queried,
 * those claims SHALL appear in consecutive rows (grouped together).
 */

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required permissions
    Permission::firstOrCreate(['name' => 'insurance.view-claims']);
    Permission::firstOrCreate(['name' => 'system.admin']);

    // Create a user with permission to view claims
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('insurance.view-claims');
});

/**
 * Dataset for random claim counts to test grouping with various sizes
 */
dataset('claim_group_sizes', function () {
    return [
        'three claims same CCC' => [3],
    ];
});

/**
 * Dataset for random number of different CCCs to test mixed scenarios
 */
dataset('mixed_ccc_scenarios', function () {
    return [
        'two shared CCCs' => [2, 2, 2], // 2 shared CCCs with 2 claims each, 2 other unique claims
    ];
});

it('groups claims with the same CCC together in consecutive rows', function (int $claimCount) {
    // Arrange: Create claims with the same CCC
    $sharedCcc = 'CC-'.now()->format('Ymd').'-'.fake()->unique()->randomNumber(4);
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create claims with the same CCC
    $sameCccClaims = [];
    for ($i = 0; $i < $claimCount; $i++) {
        $sameCccClaims[] = InsuranceClaim::factory()->create([
            'claim_check_code' => $sharedCcc,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'pending_vetting',
            'date_of_attendance' => now()->subDays($i),
        ]);
    }

    // Create some claims with different CCCs to ensure they don't interfere
    for ($i = 0; $i < 3; $i++) {
        InsuranceClaim::factory()->create([
            'claim_check_code' => 'CC-'.now()->format('Ymd').'-'.fake()->unique()->randomNumber(4),
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'pending_vetting',
        ]);
    }

    // Act: Query the claims list
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', ['status' => 'all', 'per_page' => 50]));

    $response->assertOk();

    // Get the claims data from the Inertia response
    $claims = $response->original->getData()['page']['props']['claims']['data'];

    // Assert: Find indices of claims with our shared CCC
    $indices = collect($claims)
        ->map(fn ($claim, $index) => $claim['claim_check_code'] === $sharedCcc ? $index : null)
        ->filter(fn ($index) => $index !== null)
        ->values()
        ->toArray();

    // Verify we found all our claims
    expect(count($indices))->toBe($claimCount);

    // Verify they are consecutive (each index should be exactly 1 more than the previous)
    for ($i = 1; $i < count($indices); $i++) {
        expect($indices[$i] - $indices[$i - 1])->toBe(1,
            "Claims with CCC {$sharedCcc} are not consecutive. Found at indices: ".implode(', ', $indices)
        );
    }
})->with('claim_group_sizes');

it('maintains CCC grouping with multiple shared CCCs', function (int $sharedCccCount, int $claimsPerCcc, int $uniqueClaimCount) {
    // Arrange: Create provider and plan
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create multiple groups of claims with shared CCCs
    $sharedCccs = [];
    for ($g = 0; $g < $sharedCccCount; $g++) {
        $ccc = 'CC-'.now()->format('Ymd').'-'.str_pad($g + 1, 4, '0', STR_PAD_LEFT);
        $sharedCccs[] = $ccc;

        for ($i = 0; $i < $claimsPerCcc; $i++) {
            InsuranceClaim::factory()->create([
                'claim_check_code' => $ccc,
                'patient_insurance_id' => $patientInsurance->id,
                'status' => 'pending_vetting',
                'date_of_attendance' => now()->subDays($g * 10 + $i),
            ]);
        }
    }

    // Create unique claims
    for ($i = 0; $i < $uniqueClaimCount; $i++) {
        InsuranceClaim::factory()->create([
            'claim_check_code' => 'CC-UNIQUE-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'pending_vetting',
        ]);
    }

    // Act: Query the claims list
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', ['status' => 'all', 'per_page' => 50]));

    $response->assertOk();

    // Get the claims data from the Inertia response
    $claims = $response->original->getData()['page']['props']['claims']['data'];

    // Assert: For each shared CCC, verify claims are consecutive
    foreach ($sharedCccs as $ccc) {
        $indices = collect($claims)
            ->map(fn ($claim, $index) => $claim['claim_check_code'] === $ccc ? $index : null)
            ->filter(fn ($index) => $index !== null)
            ->values()
            ->toArray();

        // Verify we found all claims for this CCC
        expect(count($indices))->toBe($claimsPerCcc);

        // Verify they are consecutive
        for ($i = 1; $i < count($indices); $i++) {
            expect($indices[$i] - $indices[$i - 1])->toBe(1,
                "Claims with CCC {$ccc} are not consecutive. Found at indices: ".implode(', ', $indices)
            );
        }
    }
})->with('mixed_ccc_scenarios');

it('sorts claims by CCC as primary sort criterion', function () {
    // Arrange: Create claims with different CCCs
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create claims with CCCs that should sort in a specific order
    $cccs = ['CC-AAA-0001', 'CC-BBB-0001', 'CC-CCC-0001'];
    foreach ($cccs as $ccc) {
        InsuranceClaim::factory()->create([
            'claim_check_code' => $ccc,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'pending_vetting',
        ]);
    }

    // Act: Query the claims list
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', ['status' => 'all', 'per_page' => 50]));

    $response->assertOk();

    // Get the claims data from the Inertia response
    $claims = $response->original->getData()['page']['props']['claims']['data'];

    // Assert: Claims should be sorted by CCC (ascending)
    $returnedCccs = collect($claims)->pluck('claim_check_code')->toArray();
    $sortedCccs = $returnedCccs;
    sort($sortedCccs);

    expect($returnedCccs)->toBe($sortedCccs,
        'Claims are not sorted by CCC. Expected: '.implode(', ', $sortedCccs).', Got: '.implode(', ', $returnedCccs)
    );
});
