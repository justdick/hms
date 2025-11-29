<?php

/**
 * Property-Based Tests for Service Override Creates Owing Record
 *
 * **Feature: billing-enhancements, Property 15: Service override creates owing record**
 * **Validates: Requirements 13.3, 13.4, 13.6**
 */

use App\Models\BillingOverride;
use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\PaymentAuditLog;
use App\Models\User;
use App\Services\OverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create required permissions
    Permission::firstOrCreate(['name' => 'billing.override']);
});

describe('Property 15: Service override creates owing record', function () {
    /**
     * **Feature: billing-enhancements, Property 15: Service override creates owing record**
     * **Validates: Requirements 13.3, 13.4, 13.6**
     *
     * For any approved service override, the charge SHALL be marked as 'owing' status
     * and an audit record SHALL be created with the authorizing user and reason.
     */
    it('marks charge as owing and creates audit record when override is created', function () {
        $overrideService = app(OverrideService::class);

        // Run 100 iterations with different random configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange: Create a patient checkin with a pending charge
            $checkin = PatientCheckin::factory()->create();
            $authorizedBy = User::factory()->create();
            $authorizedBy->givePermissionTo('billing.override');

            $charge = Charge::factory()
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                    'service_type' => fake()->randomElement(['consultation', 'laboratory', 'pharmacy', 'ward', 'procedure']),
                    'amount' => fake()->randomFloat(2, 10, 1000),
                ]);

            $reason = fake()->sentence(fake()->numberBetween(5, 15));
            $originalStatus = $charge->status;
            $originalAmount = $charge->amount;

            // Act: Create override using the service
            $override = $overrideService->createOverride($charge, $authorizedBy, $reason);

            // Refresh the charge to get updated values
            $charge->refresh();

            // Assert 1: Charge status should be 'owing'
            expect($charge->status)->toBe('owing', "Charge {$charge->id} should have status 'owing' after override");

            // Assert 2: Charge amount should remain unchanged
            expect((float) $charge->amount)->toBe((float) $originalAmount, 'Charge amount should not change after override');

            // Assert 3: BillingOverride record should be created
            expect($override)->toBeInstanceOf(BillingOverride::class);
            expect($override->charge_id)->toBe($charge->id);
            expect($override->patient_checkin_id)->toBe($checkin->id);
            expect($override->authorized_by)->toBe($authorizedBy->id);
            expect($override->reason)->toBe($reason);
            expect($override->status)->toBe(BillingOverride::STATUS_ACTIVE);
            expect($override->service_type)->toBe($charge->service_type);

            // Assert 4: Audit log entry should be created
            $auditLog = PaymentAuditLog::where('charge_id', $charge->id)
                ->where('action', PaymentAuditLog::ACTION_OVERRIDE)
                ->first();

            expect($auditLog)->not->toBeNull('Audit log should be created for override');
            expect($auditLog->user_id)->toBe($authorizedBy->id, 'Audit log should record authorizing user');
            expect($auditLog->reason)->toBe($reason, 'Audit log should record the reason');
            expect($auditLog->new_values)->toBeArray();
            expect($auditLog->new_values['status'])->toBe('owing', 'Audit log should record new status as owing');

            // Clean up for next iteration
            PaymentAuditLog::where('charge_id', $charge->id)->delete();
            $override->delete();
            $charge->delete();
            $checkin->delete();
            $authorizedBy->delete();
        }
    });

    /**
     * Property: Multiple charges can be overridden in a single operation
     */
    it('creates owing records for multiple charges when batch override is created', function () {
        $overrideService = app(OverrideService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange: Create a patient checkin with multiple pending charges
            $checkin = PatientCheckin::factory()->create();
            $authorizedBy = User::factory()->create();
            $authorizedBy->givePermissionTo('billing.override');

            $numCharges = fake()->numberBetween(2, 5);
            $charges = Charge::factory()
                ->count($numCharges)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                ]);

            $chargeIds = $charges->pluck('id')->toArray();
            $reason = fake()->sentence(fake()->numberBetween(5, 15));

            // Act: Create overrides for all charges
            $overrides = $overrideService->createOverridesForCharges($chargeIds, $authorizedBy, $reason);

            // Assert 1: All charges should be marked as owing
            $owingCharges = Charge::whereIn('id', $chargeIds)->where('status', 'owing')->get();
            expect($owingCharges->count())->toBe($numCharges, "All {$numCharges} charges should be marked as owing");

            // Assert 2: Override records should be created for each charge
            expect($overrides->count())->toBe($numCharges, 'Override records should be created for each charge');

            // Assert 3: Audit logs should be created for each charge
            $auditLogs = PaymentAuditLog::whereIn('charge_id', $chargeIds)
                ->where('action', PaymentAuditLog::ACTION_OVERRIDE)
                ->get();
            expect($auditLogs->count())->toBe($numCharges, 'Audit logs should be created for each charge');

            // Assert 4: All audit logs should have the same authorizing user and reason
            foreach ($auditLogs as $log) {
                expect($log->user_id)->toBe($authorizedBy->id);
                expect($log->reason)->toBe($reason);
            }

            // Clean up
            PaymentAuditLog::whereIn('charge_id', $chargeIds)->delete();
            BillingOverride::whereIn('charge_id', $chargeIds)->delete();
            Charge::whereIn('id', $chargeIds)->delete();
            $checkin->delete();
            $authorizedBy->delete();
        }
    });

    /**
     * Property: Override status check returns correct information
     */
    it('returns correct override status for charges', function () {
        $overrideService = app(OverrideService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $authorizedBy = User::factory()->create();

            $charge = Charge::factory()
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                ]);

            // Assert: Before override, status should show no override
            $statusBefore = $overrideService->checkOverrideStatus($charge);
            expect($statusBefore['has_override'])->toBeFalse();
            expect($statusBefore['is_active'])->toBeFalse();

            // Act: Create override
            $reason = fake()->sentence(10);
            $override = $overrideService->createOverride($charge, $authorizedBy, $reason);

            // Assert: After override, status should show active override
            $statusAfter = $overrideService->checkOverrideStatus($charge);
            expect($statusAfter['has_override'])->toBeTrue();
            expect($statusAfter['is_active'])->toBeTrue();
            expect($statusAfter['override']['reason'])->toBe($reason);
            expect($statusAfter['override']['authorized_by'])->toBe($authorizedBy->name);

            // Clean up
            PaymentAuditLog::where('charge_id', $charge->id)->delete();
            $override->delete();
            $charge->delete();
            $checkin->delete();
            $authorizedBy->delete();
        }
    });

    /**
     * Property: Owing charges are correctly identified
     */
    it('correctly identifies and totals owing charges for a patient', function () {
        $overrideService = app(OverrideService::class);

        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $checkin = PatientCheckin::factory()->create();
            $authorizedBy = User::factory()->create();

            // Create mix of pending and owing charges
            $numOwing = fake()->numberBetween(1, 3);
            $numPending = fake()->numberBetween(1, 3);

            $owingCharges = Charge::factory()
                ->count($numOwing)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                ]);

            $pendingCharges = Charge::factory()
                ->count($numPending)
                ->pending()
                ->create([
                    'patient_checkin_id' => $checkin->id,
                ]);

            // Calculate expected owing total
            $expectedOwingTotal = $owingCharges->sum('amount');

            // Act: Create overrides for owing charges only
            $owingChargeIds = $owingCharges->pluck('id')->toArray();
            $overrideService->createOverridesForCharges($owingChargeIds, $authorizedBy, 'Test override');

            // Assert: Owing charges should be correctly identified
            $retrievedOwingCharges = $overrideService->getOwingCharges($checkin);
            expect($retrievedOwingCharges->count())->toBe($numOwing);

            // Assert: Total owing amount should match
            $totalOwing = $overrideService->getTotalOwingAmount($checkin);
            expect((float) $totalOwing)->toBe((float) $expectedOwingTotal);

            // Clean up
            $allChargeIds = array_merge($owingChargeIds, $pendingCharges->pluck('id')->toArray());
            PaymentAuditLog::whereIn('charge_id', $allChargeIds)->delete();
            BillingOverride::whereIn('charge_id', $owingChargeIds)->delete();
            Charge::whereIn('id', $allChargeIds)->delete();
            $checkin->delete();
            $authorizedBy->delete();
        }
    });
});
