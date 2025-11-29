<?php

/**
 * Property-Based Tests for Void and Refund Functionality
 *
 * **Feature: billing-enhancements, Property: Void maintains original and creates reversal**
 * **Validates: Requirements 7.3**
 */

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\PaymentAuditLog;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed payment methods
    PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash', 'is_active' => true, 'requires_reference' => false]
    );

    // Create billing permissions
    Permission::firstOrCreate(['name' => 'billing.void', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'billing.refund', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'billing.view-all', 'guard_name' => 'web']);
});

describe('Property: Void maintains original and creates reversal', function () {
    /**
     * **Feature: billing-enhancements, Property: Void maintains original and creates reversal**
     * **Validates: Requirements 7.3**
     *
     * For any voided payment, the original record SHALL be maintained and marked as voided,
     * and an audit log entry SHALL be created with the void action, user, reason, and timestamp.
     */
    it('maintains original record and creates audit log when voiding payment', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.void');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $originalAmount = fake()->randomFloat(2, 10, 500);
            $originalPaidAmount = fake()->randomFloat(2, 10, $originalAmount);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'amount' => $originalAmount,
                'paid_amount' => $originalPaidAmount,
                'status' => fake()->randomElement(['paid', 'partial']),
                'paid_at' => now(),
                'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration + 1, 4, '0', STR_PAD_LEFT),
            ]);

            $reason = fake()->sentence(5).' '.fake()->sentence(3); // Ensure > 10 chars

            // Act: Void the payment
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/void", [
                    'reason' => $reason,
                ]);

            // Assert: Response is successful redirect
            $response->assertRedirect();

            // Refresh the charge
            $charge->refresh();

            // Assert: Original record is maintained but marked as voided
            expect($charge->id)->not->toBeNull(
                "Original charge record should be maintained (iteration {$iteration})");
            expect($charge->status)->toBe('voided',
                "Charge status should be 'voided' (iteration {$iteration})");
            expect(round((float) $charge->amount, 2))->toBe(round($originalAmount, 2),
                "Original amount should be preserved (iteration {$iteration})");

            // Assert: Audit log entry is created
            $auditLog = PaymentAuditLog::where('charge_id', $charge->id)
                ->where('action', PaymentAuditLog::ACTION_VOID)
                ->first();

            expect($auditLog)->not->toBeNull(
                "Audit log entry should be created for void action (iteration {$iteration})");
            expect($auditLog->user_id)->toBe($user->id,
                "Audit log should record the user who voided (iteration {$iteration})");
            expect($auditLog->reason)->toBe($reason,
                "Audit log should record the void reason (iteration {$iteration})");
            expect($auditLog->created_at)->not->toBeNull(
                "Audit log should have timestamp (iteration {$iteration})");

            // Assert: Old values are preserved in audit log
            expect($auditLog->old_values)->toBeArray();
            expect($auditLog->new_values)->toBeArray();
            expect($auditLog->new_values['status'])->toBe('voided');

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Only paid or partial charges can be voided
     */
    it('prevents voiding non-paid charges', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.void');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $invalidStatus = fake()->randomElement(['pending', 'voided', 'refunded', 'owing']);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => $invalidStatus,
            ]);

            // Act: Try to void the payment
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/void", [
                    'reason' => 'Test void reason for invalid status',
                ]);

            // Assert: Should fail with error
            $response->assertSessionHasErrors('error');

            // Assert: Charge status unchanged
            $charge->refresh();
            expect($charge->status)->toBe($invalidStatus,
                "Charge status should remain unchanged (iteration {$iteration})");

            // Assert: No audit log created
            $auditLog = PaymentAuditLog::where('charge_id', $charge->id)
                ->where('action', PaymentAuditLog::ACTION_VOID)
                ->first();
            expect($auditLog)->toBeNull(
                "No audit log should be created for failed void (iteration {$iteration})");

            // Clean up
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Void requires a reason
     */
    it('requires reason for voiding', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.void');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Act: Try to void without reason
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/void", [
                    'reason' => '', // Empty reason
                ]);

            // Assert: Should fail validation
            $response->assertSessionHasErrors('reason');

            // Assert: Charge status unchanged
            $charge->refresh();
            expect($charge->status)->toBe('paid',
                "Charge status should remain 'paid' (iteration {$iteration})");

            // Clean up
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });
});

describe('Property: Refund maintains original and creates reversal', function () {
    /**
     * **Feature: billing-enhancements, Property: Refund maintains original and creates reversal**
     * **Validates: Requirements 7.3**
     *
     * For any refunded payment, the original record SHALL be maintained and updated,
     * and an audit log entry SHALL be created with the refund action, amount, user, reason, and timestamp.
     */
    it('maintains original record and creates audit log when refunding payment', function () {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.refund');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $originalAmount = fake()->randomFloat(2, 50, 500);
            $originalPaidAmount = $originalAmount; // Full payment

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'amount' => $originalAmount,
                'paid_amount' => $originalPaidAmount,
                'status' => 'paid',
                'paid_at' => now(),
                'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.str_pad($iteration + 1000, 4, '0', STR_PAD_LEFT),
            ]);

            $reason = fake()->sentence(5).' '.fake()->sentence(3); // Ensure > 10 chars
            $refundAmount = fake()->randomFloat(2, 0.01, $originalPaidAmount);

            // Act: Refund the payment
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/refund", [
                    'reason' => $reason,
                    'refund_amount' => $refundAmount,
                ]);

            // Assert: Response is successful redirect
            $response->assertRedirect();

            // Refresh the charge
            $charge->refresh();

            // Assert: Original record is maintained
            expect($charge->id)->not->toBeNull(
                "Original charge record should be maintained (iteration {$iteration})");
            expect(round((float) $charge->amount, 2))->toBe(round($originalAmount, 2),
                "Original amount should be preserved (iteration {$iteration})");

            // Assert: Paid amount is reduced by refund amount
            $expectedPaidAmount = max(0, $originalPaidAmount - $refundAmount);
            expect(round((float) $charge->paid_amount, 2))->toBe(round($expectedPaidAmount, 2),
                "Paid amount should be reduced by refund amount (iteration {$iteration})");

            // Assert: Status is updated appropriately
            $expectedStatus = $expectedPaidAmount <= 0 ? 'refunded' : 'partial';
            expect($charge->status)->toBe($expectedStatus,
                "Status should be '{$expectedStatus}' (iteration {$iteration})");

            // Assert: Audit log entry is created
            $auditLog = PaymentAuditLog::where('charge_id', $charge->id)
                ->where('action', PaymentAuditLog::ACTION_REFUND)
                ->first();

            expect($auditLog)->not->toBeNull(
                "Audit log entry should be created for refund action (iteration {$iteration})");
            expect($auditLog->user_id)->toBe($user->id,
                "Audit log should record the user who refunded (iteration {$iteration})");
            expect($auditLog->reason)->toBe($reason,
                "Audit log should record the refund reason (iteration {$iteration})");
            expect($auditLog->created_at)->not->toBeNull(
                "Audit log should have timestamp (iteration {$iteration})");

            // Assert: Refund amount is recorded in audit log
            expect($auditLog->new_values)->toBeArray();
            expect(round((float) $auditLog->new_values['refund_amount'], 2))->toBe(round($refundAmount, 2),
                "Audit log should record refund amount (iteration {$iteration})");

            // Clean up
            $auditLog->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Full refund marks charge as refunded
     */
    it('marks charge as refunded for full refund', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.refund');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $paidAmount = fake()->randomFloat(2, 10, 500);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'amount' => $paidAmount,
                'paid_amount' => $paidAmount,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $reason = fake()->sentence(5).' '.fake()->sentence(3);

            // Act: Full refund (no refund_amount specified)
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/refund", [
                    'reason' => $reason,
                    // No refund_amount = full refund
                ]);

            // Assert
            $response->assertRedirect();
            $charge->refresh();

            expect($charge->status)->toBe('refunded',
                "Full refund should mark charge as 'refunded' (iteration {$iteration})");
            expect((float) $charge->paid_amount)->toBe(0.0,
                "Full refund should set paid_amount to 0 (iteration {$iteration})");

            // Clean up
            PaymentAuditLog::where('charge_id', $charge->id)->delete();
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Refund amount cannot exceed paid amount
     */
    it('prevents refund amount exceeding paid amount', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.refund');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $paidAmount = fake()->randomFloat(2, 10, 100);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'amount' => $paidAmount,
                'paid_amount' => $paidAmount,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $excessiveRefund = $paidAmount + fake()->randomFloat(2, 1, 100);

            // Act: Try to refund more than paid
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/refund", [
                    'reason' => 'Test refund with excessive amount',
                    'refund_amount' => $excessiveRefund,
                ]);

            // Assert: Should fail validation
            $response->assertSessionHasErrors('refund_amount');

            // Assert: Charge unchanged
            $charge->refresh();
            expect($charge->status)->toBe('paid',
                "Charge status should remain 'paid' (iteration {$iteration})");
            expect((float) $charge->paid_amount)->toBe($paidAmount,
                "Paid amount should remain unchanged (iteration {$iteration})");

            // Clean up
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Only paid or partial charges can be refunded
     */
    it('prevents refunding non-paid charges', function () {
        for ($iteration = 0; $iteration < 50; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            $user->givePermissionTo('billing.refund');
            $user->givePermissionTo('billing.view-all');

            $checkin = PatientCheckin::factory()->create();
            $invalidStatus = fake()->randomElement(['pending', 'voided', 'refunded', 'owing']);

            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => $invalidStatus,
            ]);

            // Act: Try to refund
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/refund", [
                    'reason' => 'Test refund for invalid status',
                    'refund_amount' => 10.00,
                ]);

            // Assert: Should fail with error
            $response->assertSessionHasErrors('error');

            // Assert: Charge status unchanged
            $charge->refresh();
            expect($charge->status)->toBe($invalidStatus,
                "Charge status should remain unchanged (iteration {$iteration})");

            // Clean up
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });
});

describe('Property: Void and Refund require proper authorization', function () {
    /**
     * Property: Users without billing.void permission cannot void payments
     */
    it('denies void without billing.void permission', function () {
        for ($iteration = 0; $iteration < 25; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            // No billing.void permission

            $checkin = PatientCheckin::factory()->create();
            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Act
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/void", [
                    'reason' => 'Test void without permission',
                ]);

            // Assert: Should be forbidden
            $response->assertForbidden();

            // Assert: Charge unchanged
            $charge->refresh();
            expect($charge->status)->toBe('paid',
                "Charge should remain paid without permission (iteration {$iteration})");

            // Clean up
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });

    /**
     * Property: Users without billing.refund permission cannot refund payments
     */
    it('denies refund without billing.refund permission', function () {
        for ($iteration = 0; $iteration < 25; $iteration++) {
            // Arrange
            $user = User::factory()->create();
            // No billing.refund permission

            $checkin = PatientCheckin::factory()->create();
            $charge = Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'status' => 'paid',
                'paid_at' => now(),
                'paid_amount' => 100.00,
            ]);

            // Act
            $response = $this->actingAs($user)
                ->post("/billing/accounts/charges/{$charge->id}/refund", [
                    'reason' => 'Test refund without permission',
                    'refund_amount' => 50.00,
                ]);

            // Assert: Should be forbidden
            $response->assertForbidden();

            // Assert: Charge unchanged
            $charge->refresh();
            expect($charge->status)->toBe('paid',
                "Charge should remain paid without permission (iteration {$iteration})");

            // Clean up
            $charge->delete();
            $checkin->delete();
            $user->delete();
        }
    });
});
