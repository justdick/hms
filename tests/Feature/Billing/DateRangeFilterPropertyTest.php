<?php

/**
 * Property-Based Tests for Date Range Filtering
 *
 * **Feature: billing-enhancements, Property 10: Date range filtering**
 * **Validates: Requirements 5.5**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Services\CollectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure billing permissions exist
    Permission::firstOrCreate([
        'name' => 'billing.view-all',
        'guard_name' => 'web',
    ]);
});

describe('Property 10: Date range filtering', function () {
    /**
     * **Feature: billing-enhancements, Property 10: Date range filtering**
     * **Validates: Requirements 5.5**
     *
     * For any date range filter applied to collections or reports,
     * all returned records SHALL have dates within the specified range inclusive.
     */
    it('returns only records within the specified date range', function () {
        $collectionService = app(CollectionService::class);

        // Run 100 iterations with different random date ranges and data
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate random date range (1-30 days)
            $daysAgo = fake()->numberBetween(1, 60);
            $rangeDays = fake()->numberBetween(1, 30);

            $endDate = Carbon::today()->subDays($daysAgo);
            $startDate = $endDate->copy()->subDays($rangeDays);

            // Create a cashier
            $cashier = User::factory()->create();

            // Create a patient checkin for charges
            $checkin = PatientCheckin::factory()->create();

            // Create charges at various dates (some inside range, some outside)
            $chargesInRange = [];
            $chargesOutsideRange = [];

            // Create 2-5 charges inside the range
            $numInRange = fake()->numberBetween(2, 5);
            for ($i = 0; $i < $numInRange; $i++) {
                $paidAt = fake()->dateTimeBetween(
                    $startDate->toDateTimeString(),
                    $endDate->toDateTimeString()
                );

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'paid_amount' => fake()->randomFloat(2, 10, 500),
                    'processed_by' => $cashier->id,
                ]);
                $chargesInRange[] = $charge;
            }

            // Create 1-3 charges outside the range (before start date)
            $numBefore = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $numBefore; $i++) {
                $paidAt = $startDate->copy()->subDays(fake()->numberBetween(1, 30));

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'paid_amount' => fake()->randomFloat(2, 10, 500),
                    'processed_by' => $cashier->id,
                ]);
                $chargesOutsideRange[] = $charge;
            }

            // Create 1-3 charges outside the range (after end date)
            $numAfter = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $numAfter; $i++) {
                $paidAt = $endDate->copy()->addDays(fake()->numberBetween(1, 30));

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'paid_amount' => fake()->randomFloat(2, 10, 500),
                    'processed_by' => $cashier->id,
                ]);
                $chargesOutsideRange[] = $charge;
            }

            // Get collections using the service
            $collections = $collectionService->getAllCollections($startDate, $endDate);

            // Verify all returned records are within the date range
            foreach ($collections as $collection) {
                $paidAt = Carbon::parse($collection->paid_at);

                expect($paidAt->gte($startDate->startOfDay()))->toBeTrue(
                    "Charge paid_at ({$paidAt}) should be >= start date ({$startDate}) (iteration {$iteration})"
                );
                expect($paidAt->lte($endDate->endOfDay()))->toBeTrue(
                    "Charge paid_at ({$paidAt}) should be <= end date ({$endDate}) (iteration {$iteration})"
                );
            }

            // Verify the count matches expected (only in-range charges)
            expect($collections->count())->toBe(
                count($chargesInRange),
                "Should return exactly {$numInRange} charges in range (iteration {$iteration})"
            );

            // Clean up
            foreach ($chargesInRange as $charge) {
                $charge->delete();
            }
            foreach ($chargesOutsideRange as $charge) {
                $charge->delete();
            }
            $checkin->delete();
            $cashier->delete();
        }
    });

    /**
     * Property: Collections by cashier respects date range
     */
    it('filters collections by cashier within date range', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate random date range
            $daysAgo = fake()->numberBetween(1, 30);
            $rangeDays = fake()->numberBetween(1, 14);

            $endDate = Carbon::today()->subDays($daysAgo);
            $startDate = $endDate->copy()->subDays($rangeDays);

            // Create cashiers
            $cashier1 = User::factory()->create();
            $cashier2 = User::factory()->create();

            $checkin = PatientCheckin::factory()->create();

            // Create charges for cashier1 in range
            $cashier1InRange = fake()->numberBetween(1, 3);
            $cashier1Total = 0;
            for ($i = 0; $i < $cashier1InRange; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $cashier1Total += $amount;

                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'paid_at' => fake()->dateTimeBetween(
                        $startDate->toDateTimeString(),
                        $endDate->toDateTimeString()
                    ),
                    'paid_amount' => $amount,
                    'processed_by' => $cashier1->id,
                ]);
            }

            // Create charges for cashier2 in range
            $cashier2InRange = fake()->numberBetween(1, 3);
            $cashier2Total = 0;
            for ($i = 0; $i < $cashier2InRange; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $cashier2Total += $amount;

                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'paid_at' => fake()->dateTimeBetween(
                        $startDate->toDateTimeString(),
                        $endDate->toDateTimeString()
                    ),
                    'paid_amount' => $amount,
                    'processed_by' => $cashier2->id,
                ]);
            }

            // Create charges outside range (should not be counted)
            Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => 'paid',
                'paid_at' => $startDate->copy()->subDays(5),
                'paid_amount' => 1000,
                'processed_by' => $cashier1->id,
            ]);

            // Get collections by cashier
            $collections = $collectionService->getCollectionsByCashier($startDate, $endDate);

            // Verify each cashier's totals
            $cashier1Collection = $collections->firstWhere('cashier_id', $cashier1->id);
            $cashier2Collection = $collections->firstWhere('cashier_id', $cashier2->id);

            if ($cashier1Collection) {
                expect($cashier1Collection['transaction_count'])->toBe(
                    $cashier1InRange,
                    "Cashier 1 should have {$cashier1InRange} transactions (iteration {$iteration})"
                );
                expect(round($cashier1Collection['total_amount'], 2))->toBe(
                    round($cashier1Total, 2),
                    "Cashier 1 total should match (iteration {$iteration})"
                );
            }

            if ($cashier2Collection) {
                expect($cashier2Collection['transaction_count'])->toBe(
                    $cashier2InRange,
                    "Cashier 2 should have {$cashier2InRange} transactions (iteration {$iteration})"
                );
            }

            // Clean up
            Charge::where('patient_checkin_id', $checkin->id)->delete();
            $checkin->delete();
            $cashier1->delete();
            $cashier2->delete();
        }
    });

    /**
     * Property: Collections by department respects date range
     */
    it('filters collections by department within date range', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate random date range
            $daysAgo = fake()->numberBetween(1, 30);
            $rangeDays = fake()->numberBetween(1, 14);

            $endDate = Carbon::today()->subDays($daysAgo);
            $startDate = $endDate->copy()->subDays($rangeDays);

            $cashier = User::factory()->create();

            // Create checkins in different departments
            $checkin1 = PatientCheckin::factory()->create();
            $checkin2 = PatientCheckin::factory()->create();

            // Create charges in range for checkin1
            $dept1InRange = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $dept1InRange; $i++) {
                Charge::factory()->create([
                    'patient_checkin_id' => $checkin1->id,
                    'status' => 'paid',
                    'paid_at' => fake()->dateTimeBetween(
                        $startDate->toDateTimeString(),
                        $endDate->toDateTimeString()
                    ),
                    'paid_amount' => fake()->randomFloat(2, 10, 200),
                    'processed_by' => $cashier->id,
                ]);
            }

            // Create charges outside range (should not be counted)
            Charge::factory()->create([
                'patient_checkin_id' => $checkin1->id,
                'status' => 'paid',
                'paid_at' => $startDate->copy()->subDays(10),
                'paid_amount' => 500,
                'processed_by' => $cashier->id,
            ]);

            // Get collections by department
            $collections = $collectionService->getCollectionsByDepartment($startDate, $endDate);

            // Verify department totals only include in-range charges
            $dept1Collection = $collections->firstWhere('department_id', $checkin1->department_id);

            if ($dept1Collection) {
                expect($dept1Collection['transaction_count'])->toBe(
                    $dept1InRange,
                    "Department should have {$dept1InRange} transactions (iteration {$iteration})"
                );
            }

            // Clean up
            Charge::where('patient_checkin_id', $checkin1->id)->delete();
            Charge::where('patient_checkin_id', $checkin2->id)->delete();
            $checkin1->delete();
            $checkin2->delete();
            $cashier->delete();
        }
    });

    /**
     * Property: Empty date range returns no results
     */
    it('returns empty results for date range with no data', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate a random date range far in the past where no data exists
            $daysAgo = fake()->numberBetween(1000, 2000);
            $rangeDays = fake()->numberBetween(1, 7);

            $endDate = Carbon::today()->subDays($daysAgo);
            $startDate = $endDate->copy()->subDays($rangeDays);

            // Get collections - should be empty
            $collections = $collectionService->getAllCollections($startDate, $endDate);

            expect($collections->count())->toBe(
                0,
                "Should return 0 collections for date range with no data (iteration {$iteration})"
            );
        }
    });
});
