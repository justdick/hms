<?php

/**
 * Property-Based Tests for Cashier Collections Accuracy
 *
 * **Feature: billing-enhancements, Property 3: Cashier collections accuracy**
 * **Feature: billing-enhancements, Property 4: Collections breakdown consistency**
 * **Validates: Requirements 2.1, 2.2, 2.4**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\CollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed payment methods
    PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash', 'is_active' => true, 'requires_reference' => false]
    );
    PaymentMethod::firstOrCreate(
        ['code' => 'card'],
        ['name' => 'Card', 'is_active' => true, 'requires_reference' => true]
    );
    PaymentMethod::firstOrCreate(
        ['code' => 'mobile_money'],
        ['name' => 'Mobile Money', 'is_active' => true, 'requires_reference' => true]
    );
    PaymentMethod::firstOrCreate(
        ['code' => 'bank_transfer'],
        ['name' => 'Bank Transfer', 'is_active' => true, 'requires_reference' => true]
    );
});

describe('Property 3: Cashier collections accuracy', function () {
    /**
     * **Feature: billing-enhancements, Property 3: Cashier collections accuracy**
     * **Validates: Requirements 2.1, 2.4**
     *
     * For any cashier on any date, the displayed collection total SHALL equal
     * the sum of all payments processed by that cashier on that date.
     */
    it('calculates cashier collection total accurately', function () {
        $collectionService = app(CollectionService::class);

        // Run 100 iterations with different random configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create a cashier
            $cashier = User::factory()->create();
            $date = today();

            // Create random number of paid charges for this cashier (1-10)
            $numCharges = fake()->numberBetween(1, 10);
            $checkin = PatientCheckin::factory()->create();

            $expectedTotal = 0;
            $chargeIds = [];

            for ($i = 0; $i < $numCharges; $i++) {
                $paidAmount = fake()->randomFloat(2, 10, 500);
                $paymentMethod = fake()->randomElement(['cash', 'card', 'mobile_money', 'bank_transfer']);

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $paidAmount,
                    'paid_amount' => $paidAmount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $date,
                    'metadata' => ['payment_method' => $paymentMethod],
                ]);

                $expectedTotal += $paidAmount;
                $chargeIds[] = $charge->id;
            }

            // Act: Get cashier collections using the service
            $summary = $collectionService->getCashierCollectionSummary($cashier, $date);

            // Assert: Total matches expected
            expect(round((float) $summary['total_amount'], 2))
                ->toBe(round($expectedTotal, 2),
                    "Cashier collection total should equal sum of all payments (iteration {$iteration})");

            // Assert: Transaction count matches
            expect($summary['transaction_count'])
                ->toBe($numCharges,
                    'Transaction count should match number of charges processed');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $cashier->delete();
        }
    });

    /**
     * Property: Collections only include charges processed by the specific cashier
     */
    it('only includes charges processed by the specific cashier', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create two cashiers
            $cashier1 = User::factory()->create();
            $cashier2 = User::factory()->create();
            $date = today();

            $checkin = PatientCheckin::factory()->create();

            // Create charges for cashier 1
            $cashier1Total = 0;
            $cashier1Count = fake()->numberBetween(1, 5);
            $chargeIds = [];

            for ($i = 0; $i < $cashier1Count; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier1->id,
                    'paid_at' => $date,
                ]);
                $cashier1Total += $amount;
                $chargeIds[] = $charge->id;
            }

            // Create charges for cashier 2
            $cashier2Count = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $cashier2Count; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier2->id,
                    'paid_at' => $date,
                ]);
                $chargeIds[] = $charge->id;
            }

            // Act: Get collections for cashier 1 only
            $summary = $collectionService->getCashierCollectionSummary($cashier1, $date);

            // Assert: Only cashier 1's charges are included
            expect(round((float) $summary['total_amount'], 2))
                ->toBe(round($cashier1Total, 2),
                    'Should only include charges processed by the specific cashier');

            expect($summary['transaction_count'])
                ->toBe($cashier1Count,
                    "Transaction count should only include specific cashier's transactions");

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $cashier1->delete();
            $cashier2->delete();
        }
    });

    /**
     * Property: Collections only include charges from the specified date
     */
    it('only includes charges from the specified date', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $targetDate = today();
            $otherDate = today()->subDays(fake()->numberBetween(1, 30));

            $checkin = PatientCheckin::factory()->create();

            // Create charges for target date
            $targetDateTotal = 0;
            $targetDateCount = fake()->numberBetween(1, 5);
            $chargeIds = [];

            for ($i = 0; $i < $targetDateCount; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $targetDate,
                ]);
                $targetDateTotal += $amount;
                $chargeIds[] = $charge->id;
            }

            // Create charges for other date
            $otherDateCount = fake()->numberBetween(1, 5);
            for ($i = 0; $i < $otherDateCount; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $otherDate,
                ]);
                $chargeIds[] = $charge->id;
            }

            // Act: Get collections for target date only
            $summary = $collectionService->getCashierCollectionSummary($cashier, $targetDate);

            // Assert: Only target date charges are included
            expect(round((float) $summary['total_amount'], 2))
                ->toBe(round($targetDateTotal, 2),
                    'Should only include charges from the specified date');

            expect($summary['transaction_count'])
                ->toBe($targetDateCount,
                    "Transaction count should only include specified date's transactions");

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $cashier->delete();
        }
    });
});

describe('Property 4: Collections breakdown consistency', function () {
    /**
     * **Feature: billing-enhancements, Property 4: Collections breakdown consistency**
     * **Validates: Requirements 2.2, 5.3**
     *
     * For any cashier's daily collections, the sum of amounts grouped by payment method
     * SHALL equal the total collections amount.
     */
    it('breakdown by payment method sums to total', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $date = today();
            $checkin = PatientCheckin::factory()->create();

            $paymentMethods = ['cash', 'card', 'mobile_money', 'bank_transfer'];
            $chargeIds = [];
            $expectedTotal = 0;

            // Create random charges with different payment methods
            $numCharges = fake()->numberBetween(3, 12);
            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 10, 300);
                $paymentMethod = fake()->randomElement($paymentMethods);

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $date,
                    'metadata' => ['payment_method' => $paymentMethod],
                ]);

                $expectedTotal += $amount;
                $chargeIds[] = $charge->id;
            }

            // Act: Get collections summary with breakdown
            $summary = $collectionService->getCashierCollectionSummary($cashier, $date);

            // Calculate sum of breakdown
            $breakdownSum = 0;
            foreach ($summary['breakdown'] as $method => $data) {
                $breakdownSum += $data['total_amount'];
            }

            // Assert: Breakdown sum equals total
            expect(round($breakdownSum, 2))
                ->toBe(round((float) $summary['total_amount'], 2),
                    "Sum of payment method breakdown should equal total collections (iteration {$iteration})");

            // Assert: Total matches expected
            expect(round((float) $summary['total_amount'], 2))
                ->toBe(round($expectedTotal, 2),
                    'Total should match expected sum of all charges');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $cashier->delete();
        }
    });

    /**
     * Property: Transaction counts in breakdown sum to total transaction count
     */
    it('transaction counts in breakdown sum to total', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $date = today();
            $checkin = PatientCheckin::factory()->create();

            $paymentMethods = ['cash', 'card', 'mobile_money', 'bank_transfer'];
            $chargeIds = [];

            $numCharges = fake()->numberBetween(3, 10);
            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $paymentMethod = fake()->randomElement($paymentMethods);

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $date,
                    'metadata' => ['payment_method' => $paymentMethod],
                ]);

                $chargeIds[] = $charge->id;
            }

            // Act: Get collections summary
            $summary = $collectionService->getCashierCollectionSummary($cashier, $date);

            // Calculate sum of transaction counts from breakdown
            $breakdownCountSum = 0;
            foreach ($summary['breakdown'] as $method => $data) {
                $breakdownCountSum += $data['transaction_count'];
            }

            // Assert: Breakdown count sum equals total transaction count
            expect($breakdownCountSum)
                ->toBe($summary['transaction_count'],
                    'Sum of transaction counts in breakdown should equal total transaction count');

            // Assert: Total count matches expected
            expect($summary['transaction_count'])
                ->toBe($numCharges,
                    'Total transaction count should match number of charges created');

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $cashier->delete();
        }
    });

    /**
     * Property: Each payment method in breakdown has correct individual totals
     */
    it('each payment method has correct individual totals', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $date = today();
            $checkin = PatientCheckin::factory()->create();

            $paymentMethods = ['cash', 'card', 'mobile_money', 'bank_transfer'];
            $expectedByMethod = array_fill_keys($paymentMethods, ['total' => 0, 'count' => 0]);
            $chargeIds = [];

            $numCharges = fake()->numberBetween(4, 12);
            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 10, 200);
                $paymentMethod = fake()->randomElement($paymentMethods);

                $charge = Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $date,
                    'metadata' => ['payment_method' => $paymentMethod],
                ]);

                $expectedByMethod[$paymentMethod]['total'] += $amount;
                $expectedByMethod[$paymentMethod]['count']++;
                $chargeIds[] = $charge->id;
            }

            // Act: Get collections summary
            $summary = $collectionService->getCashierCollectionSummary($cashier, $date);

            // Assert: Each payment method has correct totals
            foreach ($paymentMethods as $method) {
                $expected = $expectedByMethod[$method];
                $actual = $summary['breakdown'][$method] ?? ['total_amount' => 0, 'transaction_count' => 0];

                expect(round((float) $actual['total_amount'], 2))
                    ->toBe(round($expected['total'], 2),
                        "Payment method {$method} should have correct total amount");

                expect($actual['transaction_count'])
                    ->toBe($expected['count'],
                        "Payment method {$method} should have correct transaction count");
            }

            // Clean up
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $cashier->delete();
        }
    });

    /**
     * Property: Empty collections return zero totals
     */
    it('returns zero totals for cashier with no collections', function () {
        $collectionService = app(CollectionService::class);

        for ($iteration = 0; $iteration < 25; $iteration++) {
            // Arrange: Create a cashier with no charges
            $cashier = User::factory()->create();
            $date = today();

            // Act: Get collections summary
            $summary = $collectionService->getCashierCollectionSummary($cashier, $date);

            // Assert: All totals are zero
            expect((float) $summary['total_amount'])->toBe(0.0,
                'Total amount should be zero for cashier with no collections');

            expect($summary['transaction_count'])->toBe(0,
                'Transaction count should be zero for cashier with no collections');

            // Assert: All breakdown amounts are zero
            foreach ($summary['breakdown'] as $method => $data) {
                expect((float) $data['total_amount'])->toBe(0.0,
                    "Breakdown amount for {$method} should be zero");
                expect($data['transaction_count'])->toBe(0,
                    "Breakdown count for {$method} should be zero");
            }

            // Clean up
            $cashier->delete();
        }
    });
});
