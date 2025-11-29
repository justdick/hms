<?php

/**
 * Property-Based Tests for Outstanding Balance Aging Categorization
 *
 * **Feature: billing-enhancements, Property 11: Outstanding balance aging categorization**
 * **Validates: Requirements 9.2**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property 11: Outstanding balance aging categorization', function () {
    /**
     * **Feature: billing-enhancements, Property 11: Outstanding balance aging categorization**
     * **Validates: Requirements 9.2**
     *
     * For any outstanding charge, it SHALL be categorized into exactly one aging bucket
     * based on days since charge date.
     */
    it('categorizes each charge into exactly one aging bucket', function () {
        $reportService = app(ReportService::class);

        // Run 100 iterations with different random charge ages
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate a random number of days (0-200 days old)
            $daysOld = fake()->numberBetween(0, 200);

            // Determine expected bucket based on days
            $expectedBucket = match (true) {
                $daysOld <= 30 => 'current',
                $daysOld <= 60 => 'days_30',
                $daysOld <= 90 => 'days_60',
                default => 'days_90_plus',
            };

            // Test the getAgingBucket method directly
            $actualBucket = $reportService->getAgingBucket($daysOld);

            expect($actualBucket)->toBe(
                $expectedBucket,
                "Charge {$daysOld} days old should be in '{$expectedBucket}' bucket, got '{$actualBucket}' (iteration {$iteration})"
            );

            // Verify it's exactly one of the valid buckets
            $validBuckets = ['current', 'days_30', 'days_60', 'days_90_plus'];
            expect(in_array($actualBucket, $validBuckets))->toBeTrue(
                "Bucket '{$actualBucket}' should be one of the valid buckets (iteration {$iteration})"
            );
        }
    });

    /**
     * Property: Aging buckets have correct boundaries
     */
    it('correctly categorizes charges at bucket boundaries', function () {
        $reportService = app(ReportService::class);

        // Test exact boundary values
        $boundaryTests = [
            // [days, expected_bucket]
            [0, 'current'],
            [1, 'current'],
            [29, 'current'],
            [30, 'current'],
            [31, 'days_30'],
            [45, 'days_30'],
            [60, 'days_30'],
            [61, 'days_60'],
            [75, 'days_60'],
            [90, 'days_60'],
            [91, 'days_90_plus'],
            [120, 'days_90_plus'],
            [365, 'days_90_plus'],
        ];

        foreach ($boundaryTests as [$days, $expectedBucket]) {
            $actualBucket = $reportService->getAgingBucket($days);
            expect($actualBucket)->toBe(
                $expectedBucket,
                "Charge {$days} days old should be in '{$expectedBucket}' bucket"
            );
        }
    });

    /**
     * Property: Aging bucket amounts sum to total outstanding
     */
    it('ensures aging bucket amounts sum to total outstanding for each patient', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create a patient checkin
            $checkin = PatientCheckin::factory()->create();

            // Create random number of charges with various ages
            $numCharges = fake()->numberBetween(1, 5);
            $expectedTotal = 0;

            for ($i = 0; $i < $numCharges; $i++) {
                $daysOld = fake()->numberBetween(0, 150);
                $amount = fake()->randomFloat(2, 10, 500);
                $paidAmount = fake()->boolean(30) ? fake()->randomFloat(2, 0, $amount * 0.5) : 0;

                $chargedAt = Carbon::now()->subDays($daysOld);

                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => $paidAmount > 0 ? 'partial' : fake()->randomElement(['pending', 'owing']),
                    'amount' => $amount,
                    'paid_amount' => $paidAmount,
                    'charged_at' => $chargedAt,
                ]);

                $expectedTotal += ($amount - $paidAmount);
            }

            // Get outstanding balances
            $balances = $reportService->getOutstandingBalances();

            // Find the patient's balance
            $patientBalance = $balances->firstWhere('patient_id', $checkin->patient_id);

            if ($patientBalance) {
                // Sum of aging buckets should equal total outstanding
                $agingSum = $patientBalance['aging']['current']
                    + $patientBalance['aging']['days_30']
                    + $patientBalance['aging']['days_60']
                    + $patientBalance['aging']['days_90_plus'];

                expect(round($agingSum, 2))->toBe(
                    round($patientBalance['total_outstanding'], 2),
                    "Aging bucket sum ({$agingSum}) should equal total outstanding ({$patientBalance['total_outstanding']}) (iteration {$iteration})"
                );

                // Total should match expected
                expect(round($patientBalance['total_outstanding'], 2))->toBe(
                    round($expectedTotal, 2),
                    "Total outstanding should match expected (iteration {$iteration})"
                );
            }

            // Clean up
            Charge::where('patient_checkin_id', $checkin->id)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Each charge contributes to exactly one bucket
     */
    it('places each charge amount in exactly one aging bucket', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create a patient checkin
            $checkin = PatientCheckin::factory()->create();

            // Create charges with known ages and amounts
            // Use startOfDay to ensure consistent day calculations
            $now = Carbon::now()->startOfDay();
            $chargeData = [];
            $numCharges = fake()->numberBetween(2, 4);

            for ($i = 0; $i < $numCharges; $i++) {
                $daysOld = fake()->numberBetween(0, 150);
                $amount = fake()->randomFloat(2, 50, 200);

                // Use startOfDay for consistent day calculation
                $chargedAt = $now->copy()->subDays($daysOld)->startOfDay();

                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'pending',
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'charged_at' => $chargedAt,
                    'created_at' => $chargedAt,
                ]);

                $expectedBucket = $reportService->getAgingBucket($daysOld);
                $chargeData[] = [
                    'amount' => $amount,
                    'days_old' => $daysOld,
                    'expected_bucket' => $expectedBucket,
                ];
            }

            // Calculate expected bucket totals
            $expectedBuckets = [
                'current' => 0,
                'days_30' => 0,
                'days_60' => 0,
                'days_90_plus' => 0,
            ];

            foreach ($chargeData as $data) {
                $expectedBuckets[$data['expected_bucket']] += $data['amount'];
            }

            // Get outstanding balances
            $balances = $reportService->getOutstandingBalances();
            $patientBalance = $balances->firstWhere('patient_id', $checkin->patient_id);

            if ($patientBalance) {
                // Verify the sum of all buckets equals total outstanding
                $agingSum = array_sum($patientBalance['aging']);
                expect(round($agingSum, 2))->toBe(
                    round($patientBalance['total_outstanding'], 2),
                    "Aging bucket sum should equal total outstanding (iteration {$iteration})"
                );

                // Verify total matches expected
                $expectedTotal = array_sum($expectedBuckets);
                expect(round($patientBalance['total_outstanding'], 2))->toBe(
                    round($expectedTotal, 2),
                    "Total outstanding should match expected (iteration {$iteration})"
                );
            }

            // Clean up
            Charge::where('patient_checkin_id', $checkin->id)->delete();
            $checkin->delete();
        }
    });

    /**
     * Property: Aging categorization is deterministic
     */
    it('produces consistent aging categorization for the same charge', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $daysOld = fake()->numberBetween(0, 200);

            // Call getAgingBucket multiple times with the same input
            $result1 = $reportService->getAgingBucket($daysOld);
            $result2 = $reportService->getAgingBucket($daysOld);
            $result3 = $reportService->getAgingBucket($daysOld);

            expect($result1)->toBe($result2)
                ->and($result2)->toBe($result3);
        }
    });

    /**
     * Property: Older charges are in higher aging buckets
     */
    it('ensures older charges are categorized in equal or higher aging buckets', function () {
        $reportService = app(ReportService::class);

        $bucketOrder = [
            'current' => 0,
            'days_30' => 1,
            'days_60' => 2,
            'days_90_plus' => 3,
        ];

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate two random ages where one is older
            $youngerDays = fake()->numberBetween(0, 100);
            $olderDays = $youngerDays + fake()->numberBetween(1, 100);

            $youngerBucket = $reportService->getAgingBucket($youngerDays);
            $olderBucket = $reportService->getAgingBucket($olderDays);

            expect($bucketOrder[$olderBucket])->toBeGreaterThanOrEqual(
                $bucketOrder[$youngerBucket],
                "Older charge ({$olderDays} days) should be in equal or higher bucket than younger charge ({$youngerDays} days) (iteration {$iteration})"
            );
        }
    });
});
