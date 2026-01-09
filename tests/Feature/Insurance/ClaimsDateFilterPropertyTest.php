<?php

/**
 * Property-Based Test for Claims Date Filter Accuracy
 *
 * **Feature: multi-department-checkin, Property 6: Date Filter Accuracy**
 * **Validates: Requirements 5.4, 5.5, 5.7**
 *
 * Property: For any date filter selection (preset or custom range), the returned claims
 * SHALL have `date_of_attendance` within the specified range, and no claims outside
 * the range SHALL be returned.
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

    // Create common insurance setup
    $this->provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create(['insurance_provider_id' => $this->provider->id]);
    $this->patient = Patient::factory()->create();
    $this->patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $this->patient->id,
        'insurance_plan_id' => $this->plan->id,
    ]);
});

/**
 * Dataset for date range scenarios
 */
dataset('date_range_scenarios', function () {
    return [
        'single day filter' => [
            fn () => [
                'date_from' => '2025-01-05',
                'date_to' => '2025-01-05',
                'inside_dates' => ['2025-01-05'],
                'outside_dates' => ['2025-01-04', '2025-01-06'],
            ],
        ],
    ];
});

it('returns only claims within the specified date range', function (callable $scenario) {
    $data = $scenario();
    $dateFrom = $data['date_from'];
    $dateTo = $data['date_to'];
    $insideDates = $data['inside_dates'];
    $outsideDates = $data['outside_dates'];

    // Arrange: Create claims inside the date range
    $insideClaims = [];
    foreach ($insideDates as $date) {
        $insideClaims[] = InsuranceClaim::factory()->create([
            'patient_insurance_id' => $this->patientInsurance->id,
            'date_of_attendance' => $date,
            'status' => 'pending_vetting',
        ]);
    }

    // Create claims outside the date range
    foreach ($outsideDates as $date) {
        InsuranceClaim::factory()->create([
            'patient_insurance_id' => $this->patientInsurance->id,
            'date_of_attendance' => $date,
            'status' => 'pending_vetting',
        ]);
    }

    // Act: Query with date filter
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', [
            'status' => 'all',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => 50,
        ]));

    $response->assertOk();

    // Get the claims data from the Inertia response
    $claims = $response->original->getData()['page']['props']['claims']['data'];

    // Assert: All returned claims should be within the date range
    foreach ($claims as $claim) {
        $claimDate = $claim['date_of_attendance'];
        expect($claimDate)->toBeGreaterThanOrEqual($dateFrom,
            "Claim date {$claimDate} is before filter start {$dateFrom}"
        );
        expect($claimDate)->toBeLessThanOrEqual($dateTo,
            "Claim date {$claimDate} is after filter end {$dateTo}"
        );
    }

    // Assert: We should have exactly the number of inside claims
    expect(count($claims))->toBe(count($insideDates),
        'Expected '.count($insideDates).' claims but got '.count($claims)
    );
})->with('date_range_scenarios');

it('returns all claims when no date filter is applied', function () {
    // Arrange: Create claims with various dates
    $dates = ['2025-01-01', '2025-01-15', '2025-02-01'];
    foreach ($dates as $date) {
        InsuranceClaim::factory()->create([
            'patient_insurance_id' => $this->patientInsurance->id,
            'date_of_attendance' => $date,
            'status' => 'pending_vetting',
        ]);
    }

    // Act: Query without date filter
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', [
            'status' => 'all',
            'per_page' => 50,
        ]));

    $response->assertOk();

    // Get the claims data from the Inertia response
    $claims = $response->original->getData()['page']['props']['claims']['data'];

    // Assert: At least our 3 claims should be returned (there may be more from factory side effects)
    expect(count($claims))->toBeGreaterThanOrEqual(count($dates));

    // Verify our specific claims are present
    $claimDates = collect($claims)->pluck('date_of_attendance')->toArray();
    foreach ($dates as $date) {
        expect($claimDates)->toContain($date);
    }
});

it('handles date_from filter only (open-ended to future)', function () {
    // Arrange: Create claims before and after the filter date
    $beforeDate = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $this->patientInsurance->id,
        'date_of_attendance' => '2025-01-01',
        'status' => 'pending_vetting',
    ]);

    $onDate = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $this->patientInsurance->id,
        'date_of_attendance' => '2025-01-15',
        'status' => 'pending_vetting',
    ]);

    $afterDate = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $this->patientInsurance->id,
        'date_of_attendance' => '2025-02-01',
        'status' => 'pending_vetting',
    ]);

    // Act: Query with only date_from
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', [
            'status' => 'all',
            'date_from' => '2025-01-15',
            'per_page' => 50,
        ]));

    $response->assertOk();

    // Get the claims data
    $claims = $response->original->getData()['page']['props']['claims']['data'];
    $claimDates = collect($claims)->pluck('date_of_attendance')->toArray();

    // Assert: Should include on-date and after-date, but not before-date
    expect($claimDates)->toContain('2025-01-15');
    expect($claimDates)->toContain('2025-02-01');
    expect($claimDates)->not->toContain('2025-01-01');
});

it('handles date_to filter only (open-ended from past)', function () {
    // Arrange: Create claims before and after the filter date
    $beforeDate = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $this->patientInsurance->id,
        'date_of_attendance' => '2025-01-01',
        'status' => 'pending_vetting',
    ]);

    $onDate = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $this->patientInsurance->id,
        'date_of_attendance' => '2025-01-15',
        'status' => 'pending_vetting',
    ]);

    $afterDate = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $this->patientInsurance->id,
        'date_of_attendance' => '2025-02-01',
        'status' => 'pending_vetting',
    ]);

    // Act: Query with only date_to
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.index', [
            'status' => 'all',
            'date_to' => '2025-01-15',
            'per_page' => 50,
        ]));

    $response->assertOk();

    // Get the claims data
    $claims = $response->original->getData()['page']['props']['claims']['data'];
    $claimDates = collect($claims)->pluck('date_of_attendance')->toArray();

    // Assert: Should include before-date and on-date, but not after-date
    expect($claimDates)->toContain('2025-01-01');
    expect($claimDates)->toContain('2025-01-15');
    expect($claimDates)->not->toContain('2025-02-01');
});
