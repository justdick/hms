<?php

/**
 * Property-Based Test for Claims Summary Report Accuracy
 *
 * **Feature: nhis-claims-integration, Property 28: Claims Summary Report Accuracy**
 * **Validates: Requirements 18.1**
 *
 * Property: For any claims summary report for a period, the totals should accurately
 * reflect the sum of all claims in that period grouped by status.
 */

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    \Illuminate\Support\Facades\Cache::flush();
    InsuranceClaim::query()->delete();
    Permission::firstOrCreate(['name' => 'insurance.view-reports']);
});

/**
 * Generate random claim distributions for property testing
 */
dataset('random_claim_distributions', function () {
    return [
        [['draft' => 2, 'pending_vetting' => 3, 'vetted' => 1, 'submitted' => 2, 'approved' => 1, 'rejected' => 1, 'paid' => 2]],
        [['draft' => 0, 'pending_vetting' => 5, 'vetted' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0, 'paid' => 0]],
        [['draft' => 1, 'pending_vetting' => 1, 'vetted' => 1, 'submitted' => 1, 'approved' => 1, 'rejected' => 1, 'paid' => 1]],
        [['draft' => 0, 'pending_vetting' => 0, 'vetted' => 0, 'submitted' => 0, 'approved' => 3, 'rejected' => 2, 'paid' => 5]],
    ];
});

it('accurately calculates total claims count by status', function (array $distribution) {
    // Arrange: Create provider and plan
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Use a specific date range that won't conflict with other tests
    $specificDate = now()->subYear()->startOfMonth()->addDays(15);
    $dateFrom = now()->subYear()->startOfMonth();
    $dateTo = now()->subYear()->endOfMonth();

    // Create claims with different statuses
    foreach ($distribution as $status => $count) {
        for ($i = 0; $i < $count; $i++) {
            InsuranceClaim::factory()->create([
                'patient_id' => $patient->id,
                'patient_insurance_id' => $patientInsurance->id,
                'status' => $status,
                'date_of_attendance' => $specificDate,
                'total_claim_amount' => fake()->randomFloat(2, 100, 1000),
            ]);
        }
    }

    // Act: Query the claims summary
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.claims-summary', [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ]));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Total claims count matches
    $expectedTotal = array_sum($distribution);
    expect($data['total_claims'])->toBe($expectedTotal);

    // Assert: Status breakdown matches
    foreach ($distribution as $status => $expectedCount) {
        if ($expectedCount > 0) {
            $statusData = $data['status_breakdown'][$status] ?? null;
            expect($statusData)->not->toBeNull()
                ->and((int) $statusData['count'])->toBe($expectedCount);
        }
    }
})->with('random_claim_distributions');

it('accurately calculates total claimed amount', function () {
    // Arrange
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Use a specific date range that won't conflict with other tests
    $specificDate = now()->subYears(2)->startOfMonth()->addDays(15);
    $dateFrom = now()->subYears(2)->startOfMonth();
    $dateTo = now()->subYears(2)->endOfMonth();

    // Create claims with known amounts
    $amounts = [150.50, 275.25, 500.00, 125.75, 300.00];
    foreach ($amounts as $amount) {
        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'pending_vetting',
            'date_of_attendance' => $specificDate,
            'total_claim_amount' => $amount,
        ]);
    }

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.claims-summary', [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ]));

    $response->assertOk();
    $data = $response->json('data');

    // Assert
    $expectedTotal = array_sum($amounts);
    expect($data['total_claimed_amount'])->toBe($expectedTotal);
});

it('accurately calculates approved and paid amounts', function () {
    // Arrange
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $dateFrom = now()->startOfMonth();
    $dateTo = now()->endOfMonth();

    // Create approved claims
    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'approved',
        'date_of_attendance' => fake()->dateTimeBetween($dateFrom, $dateTo),
        'total_claim_amount' => 500.00,
        'approved_amount' => 450.00,
        'payment_amount' => 0,
    ]);

    // Create paid claims
    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'paid',
        'date_of_attendance' => fake()->dateTimeBetween($dateFrom, $dateTo),
        'total_claim_amount' => 300.00,
        'approved_amount' => 280.00,
        'payment_amount' => 280.00,
    ]);

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.claims-summary', [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ]));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Approved amount = 450 + 280 = 730
    expect((float) $data['total_approved_amount'])->toEqual(730.0);
    // Assert: Paid amount = 280
    expect((float) $data['total_paid_amount'])->toEqual(280.0);
    // Assert: Outstanding = 730 - 280 = 450
    expect((float) $data['outstanding_amount'])->toEqual(450.0);
});
