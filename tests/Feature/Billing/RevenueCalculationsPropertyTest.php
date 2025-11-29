<?php

/**
 * Property-Based Tests for Revenue Report Calculations
 *
 * **Feature: billing-enhancements, Property: Revenue totals match sum of grouped values**
 * **Validates: Requirements 10.2**
 */

use App\Models\Charge;
use App\Models\Department;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Property: Revenue totals match sum of grouped values', function () {
    /**
     * **Feature: billing-enhancements, Property: Revenue totals match sum of grouped values**
     * **Validates: Requirements 10.2**
     *
     * For any revenue report, the total revenue SHALL equal the sum of all grouped values.
     */
    it('ensures total revenue equals sum of grouped values for date grouping', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create random paid charges within a date range
            $numCharges = fake()->numberBetween(1, 10);
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $expectedTotal = 0;
            $createdCharges = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $expectedTotal += $paidAmount;
                $createdCharges[] = $charge;
            }

            // Get revenue report grouped by date
            $report = $reportService->getRevenueReport($startDate, $endDate, 'date');

            // Sum of grouped values
            $groupedSum = collect($report['grouped_data'])->sum('total');

            // Verify total equals sum of grouped values
            expect(round($groupedSum, 2))->toBe(
                round($report['summary']['total_revenue'], 2),
                "Grouped sum ({$groupedSum}) should equal total revenue ({$report['summary']['total_revenue']}) (iteration {$iteration})"
            );

            // Verify total matches expected
            expect(round($report['summary']['total_revenue'], 2))->toBe(
                round($expectedTotal, 2),
                "Total revenue should match expected total (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
        }
    });

    /**
     * Property: Revenue grouped by department sums to total
     */
    it('ensures total revenue equals sum of grouped values for department grouping', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create departments
            $departments = Department::factory()->count(fake()->numberBetween(2, 4))->create();

            $numCharges = fake()->numberBetween(3, 10);
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $expectedTotal = 0;
            $createdCharges = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);
                $department = $departments->random();

                $checkin = PatientCheckin::factory()->create([
                    'department_id' => $department->id,
                ]);
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $expectedTotal += $paidAmount;
                $createdCharges[] = $charge;
            }

            // Get revenue report grouped by department
            $report = $reportService->getRevenueReport($startDate, $endDate, 'department');

            // Sum of grouped values
            $groupedSum = collect($report['grouped_data'])->sum('total');

            // Verify total equals sum of grouped values
            expect(round($groupedSum, 2))->toBe(
                round($report['summary']['total_revenue'], 2),
                "Grouped sum ({$groupedSum}) should equal total revenue ({$report['summary']['total_revenue']}) (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
            foreach ($departments as $department) {
                $department->delete();
            }
        }
    });

    /**
     * Property: Revenue grouped by service type sums to total
     */
    it('ensures total revenue equals sum of grouped values for service type grouping', function () {
        $reportService = app(ReportService::class);

        $serviceTypes = ['consultation', 'medication', 'lab_test', 'procedure', 'ward'];

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $numCharges = fake()->numberBetween(3, 10);
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $expectedTotal = 0;
            $createdCharges = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);
                $serviceType = fake()->randomElement($serviceTypes);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'service_type' => $serviceType,
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $expectedTotal += $paidAmount;
                $createdCharges[] = $charge;
            }

            // Get revenue report grouped by service type
            $report = $reportService->getRevenueReport($startDate, $endDate, 'service_type');

            // Sum of grouped values
            $groupedSum = collect($report['grouped_data'])->sum('total');

            // Verify total equals sum of grouped values
            expect(round($groupedSum, 2))->toBe(
                round($report['summary']['total_revenue'], 2),
                "Grouped sum ({$groupedSum}) should equal total revenue ({$report['summary']['total_revenue']}) (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
        }
    });

    /**
     * Property: Revenue grouped by cashier sums to total
     */
    it('ensures total revenue equals sum of grouped values for cashier grouping', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create cashiers
            $cashiers = User::factory()->count(fake()->numberBetween(2, 4))->create();

            $numCharges = fake()->numberBetween(3, 10);
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $expectedTotal = 0;
            $createdCharges = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);
                $cashier = $cashiers->random();

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                    'processed_by' => $cashier->id,
                ]);

                $expectedTotal += $paidAmount;
                $createdCharges[] = $charge;
            }

            // Get revenue report grouped by cashier
            $report = $reportService->getRevenueReport($startDate, $endDate, 'cashier');

            // Sum of grouped values
            $groupedSum = collect($report['grouped_data'])->sum('total');

            // Verify total equals sum of grouped values
            expect(round($groupedSum, 2))->toBe(
                round($report['summary']['total_revenue'], 2),
                "Grouped sum ({$groupedSum}) should equal total revenue ({$report['summary']['total_revenue']}) (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
            foreach ($cashiers as $cashier) {
                $cashier->delete();
            }
        }
    });

    /**
     * Property: Transaction count matches sum of grouped counts
     */
    it('ensures transaction count equals sum of grouped counts', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $numCharges = fake()->numberBetween(1, 10);
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $createdCharges = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $createdCharges[] = $charge;
            }

            // Get revenue report
            $report = $reportService->getRevenueReport($startDate, $endDate, 'date');

            // Sum of grouped counts
            $groupedCount = collect($report['grouped_data'])->sum('count');

            // Verify count equals sum of grouped counts
            expect($groupedCount)->toBe(
                $report['summary']['transaction_count'],
                "Grouped count ({$groupedCount}) should equal transaction count ({$report['summary']['transaction_count']}) (iteration {$iteration})"
            );

            // Verify count matches expected
            expect($report['summary']['transaction_count'])->toBe(
                $numCharges,
                "Transaction count should match number of charges created (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
        }
    });

    /**
     * Property: Average transaction is correctly calculated
     */
    it('ensures average transaction equals total divided by count', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $numCharges = fake()->numberBetween(1, 10);
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $createdCharges = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $createdCharges[] = $charge;
            }

            // Get revenue report
            $report = $reportService->getRevenueReport($startDate, $endDate, 'date');

            // Calculate expected average
            $expectedAverage = $report['summary']['transaction_count'] > 0
                ? $report['summary']['total_revenue'] / $report['summary']['transaction_count']
                : 0;

            // Verify average is correctly calculated
            expect(round($report['summary']['average_transaction'], 2))->toBe(
                round($expectedAverage, 2),
                "Average transaction should equal total / count (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
        }
    });

    /**
     * Property: Only paid charges are included in revenue
     */
    it('excludes non-paid charges from revenue calculations', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $startDate = Carbon::now()->subDays(30);
            $endDate = Carbon::now();

            $createdCharges = [];
            $expectedTotal = 0;

            // Create some paid charges
            $numPaid = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $numPaid; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $expectedTotal += $paidAmount;
                $createdCharges[] = $charge;
            }

            // Create some non-paid charges (pending, owing, voided)
            $nonPaidStatuses = ['pending', 'owing', 'voided', 'partial'];
            $numNonPaid = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $numNonPaid; $i++) {
                $amount = fake()->randomFloat(2, 10, 500);
                $status = fake()->randomElement($nonPaidStatuses);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => $status,
                    'amount' => $amount,
                    'paid_amount' => $status === 'partial' ? $amount * 0.5 : 0,
                    'paid_at' => $status === 'partial' ? fake()->dateTimeBetween($startDate, $endDate) : null,
                ]);

                $createdCharges[] = $charge;
            }

            // Get revenue report
            $report = $reportService->getRevenueReport($startDate, $endDate, 'date');

            // Verify only paid charges are included
            expect(round($report['summary']['total_revenue'], 2))->toBe(
                round($expectedTotal, 2),
                "Total revenue should only include paid charges (iteration {$iteration})"
            );

            expect($report['summary']['transaction_count'])->toBe(
                $numPaid,
                "Transaction count should only include paid charges (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
        }
    });

    /**
     * Property: Date range filtering is accurate
     */
    it('only includes charges within the specified date range', function () {
        $reportService = app(ReportService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $startDate = Carbon::now()->subDays(15);
            $endDate = Carbon::now()->subDays(5);

            $createdCharges = [];
            $expectedTotal = 0;
            $expectedCount = 0;

            // Create charges within range
            $numInRange = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $numInRange; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = fake()->dateTimeBetween($startDate, $endDate);

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $expectedTotal += $paidAmount;
                $expectedCount++;
                $createdCharges[] = $charge;
            }

            // Create charges outside range (before start)
            $numBefore = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $numBefore; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = $startDate->copy()->subDays(fake()->numberBetween(1, 10));

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $createdCharges[] = $charge;
            }

            // Create charges outside range (after end)
            $numAfter = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $numAfter; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paidAt = $endDate->copy()->addDays(fake()->numberBetween(1, 10));

                $checkin = PatientCheckin::factory()->create();
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'status' => 'paid',
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'paid_at' => $paidAt,
                ]);

                $createdCharges[] = $charge;
            }

            // Get revenue report
            $report = $reportService->getRevenueReport($startDate, $endDate, 'date');

            // Verify only charges within range are included
            expect(round($report['summary']['total_revenue'], 2))->toBe(
                round($expectedTotal, 2),
                "Total revenue should only include charges within date range (iteration {$iteration})"
            );

            expect($report['summary']['transaction_count'])->toBe(
                $expectedCount,
                "Transaction count should only include charges within date range (iteration {$iteration})"
            );

            // Clean up
            foreach ($createdCharges as $charge) {
                $charge->delete();
            }
        }
    });
});
