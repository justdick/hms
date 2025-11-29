<?php

/**
 * Property-Based Tests for Cash Reconciliation
 *
 * **Feature: billing-enhancements, Property 8: Reconciliation variance calculation**
 * **Feature: billing-enhancements, Property 9: Reconciliation validation**
 * **Validates: Requirements 6.3, 6.4**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create billing permissions
    $permissions = [
        'billing.collect',
        'billing.view-all',
        'billing.reconcile',
        'billing.view-dept',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create finance officer role with reconcile permission
    $financeRole = Role::firstOrCreate(['name' => 'finance_officer', 'guard_name' => 'web']);
    $financeRole->givePermissionTo('billing.reconcile');
    $financeRole->givePermissionTo('billing.view-all');
});

describe('Property 8: Reconciliation variance calculation', function () {
    /**
     * **Feature: billing-enhancements, Property 8: Reconciliation variance calculation**
     * **Validates: Requirements 6.3**
     *
     * For any reconciliation, the variance SHALL equal physical count minus system total.
     */
    it('calculates variance as physical count minus system total', function () {
        $reconciliationService = app(ReconciliationService::class);

        // Run 100 iterations with different random values
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Generate random system total and physical count
            $systemTotal = fake()->randomFloat(2, 100, 10000);
            $physicalCount = fake()->randomFloat(2, 100, 10000);

            // Calculate expected variance
            $expectedVariance = round($physicalCount - $systemTotal, 2);

            // Act: Calculate variance using the service
            $actualVariance = $reconciliationService->calculateVariance($systemTotal, $physicalCount);

            // Assert: Variance equals physical count minus system total
            expect($actualVariance)
                ->toBe($expectedVariance,
                    "Variance should equal physical count ({$physicalCount}) minus system total ({$systemTotal}) = {$expectedVariance}, got {$actualVariance} (iteration {$iteration})");
        }
    });

    /**
     * Property: Positive variance indicates overage (more cash than expected)
     */
    it('positive variance indicates overage', function () {
        $reconciliationService = app(ReconciliationService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Physical count greater than system total
            $systemTotal = fake()->randomFloat(2, 100, 5000);
            $overage = fake()->randomFloat(2, 0.01, 500);
            $physicalCount = $systemTotal + $overage;

            // Act
            $variance = $reconciliationService->calculateVariance($systemTotal, $physicalCount);

            // Assert: Variance is positive
            expect($variance)->toBeGreaterThan(0,
                "Variance should be positive when physical count ({$physicalCount}) > system total ({$systemTotal})");
        }
    });

    /**
     * Property: Negative variance indicates shortage (less cash than expected)
     */
    it('negative variance indicates shortage', function () {
        $reconciliationService = app(ReconciliationService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Physical count less than system total
            $systemTotal = fake()->randomFloat(2, 500, 5000);
            $shortage = fake()->randomFloat(2, 0.01, 400);
            $physicalCount = $systemTotal - $shortage;

            // Act
            $variance = $reconciliationService->calculateVariance($systemTotal, $physicalCount);

            // Assert: Variance is negative
            expect($variance)->toBeLessThan(0,
                "Variance should be negative when physical count ({$physicalCount}) < system total ({$systemTotal})");
        }
    });

    /**
     * Property: Zero variance when physical count equals system total
     */
    it('zero variance when counts match', function () {
        $reconciliationService = app(ReconciliationService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Physical count equals system total
            $amount = fake()->randomFloat(2, 100, 10000);

            // Act
            $variance = $reconciliationService->calculateVariance($amount, $amount);

            // Assert: Variance is zero
            expect($variance)->toBe(0.0,
                'Variance should be zero when physical count equals system total');
        }
    });

    /**
     * Property: Variance is correctly stored in reconciliation record
     */
    it('stores correct variance in reconciliation record', function () {
        $reconciliationService = app(ReconciliationService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $financeOfficer = User::factory()->create();
            $financeOfficer->assignRole('finance_officer');

            $systemTotal = fake()->randomFloat(2, 100, 5000);
            $physicalCount = fake()->randomFloat(2, 100, 5000);
            $expectedVariance = round($physicalCount - $systemTotal, 2);

            // Use a unique date for each iteration to avoid duplicate constraint
            $date = today()->subDays($iteration);

            // Act: Create reconciliation
            $reconciliation = $reconciliationService->createReconciliation([
                'cashier_id' => $cashier->id,
                'finance_officer_id' => $financeOfficer->id,
                'reconciliation_date' => $date->format('Y-m-d'),
                'system_total' => $systemTotal,
                'physical_count' => $physicalCount,
                'variance_reason' => abs($expectedVariance) >= 0.01 ? 'Test reason' : null,
            ]);

            // Assert: Stored variance matches calculated variance
            expect(round((float) $reconciliation->variance, 2))
                ->toBe($expectedVariance,
                    "Stored variance should match calculated variance (iteration {$iteration})");
        }
    });
});

describe('Property 9: Reconciliation validation', function () {
    /**
     * **Feature: billing-enhancements, Property 9: Reconciliation validation**
     * **Validates: Requirements 6.4**
     *
     * For any reconciliation with non-zero variance, the system SHALL require
     * a variance reason before saving.
     */
    it('requires reason when variance exists', function () {
        $financeOfficer = User::factory()->create();
        $financeOfficer->assignRole('finance_officer');

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a cashier with some collections
            $cashier = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();

            // Create some charges for the cashier
            $systemTotal = 0;
            $numCharges = fake()->numberBetween(1, 5);

            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 50, 500);
                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => today(),
                    'metadata' => ['payment_method' => 'cash'],
                ]);
                $systemTotal += $amount;
            }

            // Create physical count with variance (ensure it's positive)
            $variance = fake()->randomFloat(2, 10, 200);
            $physicalCount = $systemTotal + $variance; // Always positive variance for this test

            // Act: Try to create reconciliation without reason
            $response = $this->actingAs($financeOfficer)
                ->post('/billing/accounts/reconciliation', [
                    'cashier_id' => $cashier->id,
                    'reconciliation_date' => today()->format('Y-m-d'),
                    'physical_count' => $physicalCount,
                    'variance_reason' => '', // Empty reason
                ]);

            // Assert: Should fail validation
            $response->assertSessionHasErrors('variance_reason');

            // Delete the reconciliation if it was created (shouldn't be)
            Reconciliation::where('cashier_id', $cashier->id)
                ->whereDate('reconciliation_date', today())
                ->delete();
        }
    });

    /**
     * Property: Reason not required when variance is zero (balanced)
     */
    it('does not require reason when balanced', function () {
        $financeOfficer = User::factory()->create();
        $financeOfficer->assignRole('finance_officer');

        for ($iteration = 0; $iteration < 25; $iteration++) {
            // Arrange: Create a cashier with some collections
            $cashier = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();

            // Create some charges for the cashier
            $systemTotal = 0;
            $numCharges = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $numCharges; $i++) {
                $amount = fake()->randomFloat(2, 50, 500);
                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => today(),
                    'metadata' => ['payment_method' => 'cash'],
                ]);
                $systemTotal += $amount;
            }

            // Physical count equals system total (balanced)
            $physicalCount = $systemTotal;

            // Act: Create reconciliation without reason (should succeed)
            $response = $this->actingAs($financeOfficer)
                ->post('/billing/accounts/reconciliation', [
                    'cashier_id' => $cashier->id,
                    'reconciliation_date' => today()->format('Y-m-d'),
                    'physical_count' => $physicalCount,
                    'variance_reason' => null,
                ]);

            // Assert: Should succeed
            $response->assertSessionDoesntHaveErrors('variance_reason');

            // Verify reconciliation was created with balanced status
            $reconciliation = Reconciliation::where('cashier_id', $cashier->id)
                ->whereDate('reconciliation_date', today())
                ->first();

            if ($reconciliation) {
                expect($reconciliation->status)->toBe('balanced',
                    'Reconciliation should have balanced status when variance is zero');
            }

            // Delete the reconciliation for next iteration
            Reconciliation::where('cashier_id', $cashier->id)
                ->whereDate('reconciliation_date', today())
                ->delete();
        }
    });

    /**
     * Property: Reconciliation succeeds with reason when variance exists
     */
    it('succeeds with reason when variance exists', function () {
        $financeOfficer = User::factory()->create();
        $financeOfficer->assignRole('finance_officer');

        for ($iteration = 0; $iteration < 25; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();

            // Create some charges
            $systemTotal = 0;

            for ($i = 0; $i < 3; $i++) {
                $amount = fake()->randomFloat(2, 50, 300);
                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => today(),
                    'metadata' => ['payment_method' => 'cash'],
                ]);
                $systemTotal += $amount;
            }

            // Create variance (positive to ensure physical count is positive)
            $variance = fake()->randomFloat(2, 10, 100);
            $physicalCount = $systemTotal + $variance;
            $reason = fake()->sentence();

            // Act: Create reconciliation with reason
            $response = $this->actingAs($financeOfficer)
                ->post('/billing/accounts/reconciliation', [
                    'cashier_id' => $cashier->id,
                    'reconciliation_date' => today()->format('Y-m-d'),
                    'physical_count' => $physicalCount,
                    'variance_reason' => $reason,
                ]);

            // Assert: Should succeed
            $response->assertSessionDoesntHaveErrors();

            // Verify reconciliation was created
            $reconciliation = Reconciliation::where('cashier_id', $cashier->id)
                ->whereDate('reconciliation_date', today())
                ->first();

            expect($reconciliation)->not->toBeNull('Reconciliation should be created');
            expect($reconciliation->variance_reason)->toBe($reason,
                'Variance reason should be stored');
            expect($reconciliation->status)->toBe('variance',
                'Status should be variance when there is a variance');

            // Delete the reconciliation for next iteration
            Reconciliation::where('cashier_id', $cashier->id)
                ->whereDate('reconciliation_date', today())
                ->delete();
        }
    });

    /**
     * Property: Cannot create duplicate reconciliation for same cashier and date
     */
    it('prevents duplicate reconciliation for same cashier and date', function () {
        $financeOfficer = User::factory()->create();
        $financeOfficer->assignRole('finance_officer');

        for ($iteration = 0; $iteration < 25; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $checkin = PatientCheckin::factory()->create();
            $date = today()->subDays($iteration); // Use different dates to avoid conflicts between iterations

            // Create some charges
            for ($i = 0; $i < 2; $i++) {
                $amount = fake()->randomFloat(2, 50, 200);
                Charge::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => 'paid',
                    'processed_by' => $cashier->id,
                    'paid_at' => $date,
                    'metadata' => ['payment_method' => 'cash'],
                ]);
            }

            // Create first reconciliation
            Reconciliation::factory()->create([
                'cashier_id' => $cashier->id,
                'finance_officer_id' => $financeOfficer->id,
                'reconciliation_date' => $date,
            ]);

            // Act: Try to create duplicate
            $response = $this->actingAs($financeOfficer)
                ->post('/billing/accounts/reconciliation', [
                    'cashier_id' => $cashier->id,
                    'reconciliation_date' => $date->format('Y-m-d'),
                    'physical_count' => 1000,
                    'variance_reason' => 'Test',
                ]);

            // Assert: Should fail with error
            $response->assertSessionHasErrors('cashier_id');

            // Verify only one reconciliation exists
            $count = Reconciliation::where('cashier_id', $cashier->id)
                ->whereDate('reconciliation_date', $date)
                ->count();

            expect($count)->toBe(1,
                'Should only have one reconciliation per cashier per date');
        }
    });

    /**
     * Property: Status is correctly set based on variance
     */
    it('sets correct status based on variance', function () {
        $reconciliationService = app(ReconciliationService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $cashier = User::factory()->create();
            $financeOfficer = User::factory()->create();
            $financeOfficer->assignRole('finance_officer');

            $systemTotal = fake()->randomFloat(2, 100, 5000);

            // Randomly choose balanced or variance scenario
            $isBalanced = fake()->boolean();

            if ($isBalanced) {
                $physicalCount = $systemTotal;
                $expectedStatus = 'balanced';
            } else {
                $variance = fake()->randomFloat(2, 1, 500) * (fake()->boolean() ? 1 : -1);
                $physicalCount = max(0, $systemTotal + $variance); // Ensure non-negative
                $expectedStatus = 'variance';
            }

            // Use unique date for each iteration
            $date = today()->subDays($iteration);

            // Act
            $reconciliation = $reconciliationService->createReconciliation([
                'cashier_id' => $cashier->id,
                'finance_officer_id' => $financeOfficer->id,
                'reconciliation_date' => $date->format('Y-m-d'),
                'system_total' => $systemTotal,
                'physical_count' => $physicalCount,
                'variance_reason' => $expectedStatus === 'variance' ? 'Test reason' : null,
            ]);

            // Assert
            expect($reconciliation->status)->toBe($expectedStatus,
                "Status should be '{$expectedStatus}' based on variance (iteration {$iteration})");
        }
    });
});
