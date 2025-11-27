<?php

/**
 * Property-Based Test for Rejected Claim Resubmission
 *
 * **Feature: nhis-claims-integration, Property 27: Rejected Claim Resubmission**
 * **Validates: Requirements 17.5**
 *
 * Property: For any rejected claim, the system should allow it to be corrected
 * and added to a new batch for resubmission.
 */

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\User;
use App\Services\ClaimBatchService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'insurance.resubmit-claims']);
    Permission::firstOrCreate(['name' => 'insurance.edit-claims']);
    Permission::firstOrCreate(['name' => 'system.admin']);

    // Clean up
    ClaimBatchItem::query()->delete();
    ClaimBatch::query()->delete();
    InsuranceClaim::query()->delete();
});

it('allows rejected claims to be prepared for resubmission', function () {
    // Arrange
    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => 'Missing documentation',
        'total_claim_amount' => 500.00,
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    // Act
    $response = $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    // Assert
    $response->assertRedirect();
    $claim->refresh();

    expect($claim->status)->toBe('vetted');
    expect($claim->rejection_reason)->toBeNull();
    expect($claim->rejected_by)->toBeNull();
    expect($claim->rejected_at)->toBeNull();
    expect($claim->resubmission_count)->toBe(1);
    expect($claim->last_resubmitted_at)->not->toBeNull();
});

it('preserves rejection reason in notes when preparing for resubmission', function () {
    // Arrange
    $rejectionReason = 'Invalid G-DRG code selected';
    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => $rejectionReason,
        'notes' => null,
        'total_claim_amount' => 500.00,
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    // Act
    $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    // Assert
    $claim->refresh();
    expect($claim->notes)->toContain('Previous rejection reason: '.$rejectionReason);
});

it('allows prepared claim to be added to a new batch', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    // Create a rejected claim
    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => 'Incorrect tariff',
        'total_claim_amount' => 750.00,
    ]);

    // Prepare for resubmission
    $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    $claim->refresh();
    expect($claim->status)->toBe('vetted');

    // Create a new batch
    $batch = $service->createBatch(
        name: 'Resubmission Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Act - Add the prepared claim to the new batch
    $result = $service->addClaimsToBatch($batch, [$claim->id]);

    // Assert
    expect($result['added'])->toBe(1);
    expect($result['skipped'])->toBe(0);
    expect($result['errors'])->toBeEmpty();

    $batch->refresh();
    expect($batch->total_claims)->toBe(1);
    expect((float) $batch->total_amount)->toBe(750.00);
});

it('increments resubmission count on each resubmission', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => 'First rejection',
        'resubmission_count' => 0,
        'total_claim_amount' => 500.00,
    ]);

    // Act - First resubmission
    $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    $claim->refresh();
    expect($claim->resubmission_count)->toBe(1);

    // Simulate rejection again
    $claim->status = 'rejected';
    $claim->rejection_reason = 'Second rejection';
    $claim->save();

    // Act - Second resubmission
    $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    $claim->refresh();
    expect($claim->resubmission_count)->toBe(2);
});

it('clears submission data when preparing for resubmission', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => 'Rejected by NHIA',
        'submitted_by' => $user->id,
        'submitted_at' => now()->subDays(5),
        'submission_date' => now()->subDays(5)->toDateString(),
        'batch_reference' => 'BATCH-202511-0001',
        'batch_submitted_at' => now()->subDays(5),
        'total_claim_amount' => 500.00,
    ]);

    // Act
    $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    // Assert
    $claim->refresh();
    expect($claim->submitted_by)->toBeNull();
    expect($claim->submitted_at)->toBeNull();
    expect($claim->submission_date)->toBeNull();
    expect($claim->batch_reference)->toBeNull();
    expect($claim->batch_submitted_at)->toBeNull();
});

it('allows editing rejected claims before resubmission', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.edit-claims');

    $gdrgTariff = GdrgTariff::factory()->create([
        'tariff_price' => 200.00,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => 'Wrong G-DRG',
        'gdrg_tariff_id' => null,
        'gdrg_amount' => null,
        'total_claim_amount' => 300.00,
    ]);

    // Act
    $response = $this->actingAs($user)
        ->put(route('admin.insurance.claims.update', $claim), [
            'gdrg_tariff_id' => $gdrgTariff->id,
            'notes' => 'Corrected G-DRG selection',
        ]);

    // Assert
    $response->assertRedirect();
    $claim->refresh();

    expect($claim->gdrg_tariff_id)->toBe($gdrgTariff->id);
    expect((float) $claim->gdrg_amount)->toBe(200.00);
    expect($claim->notes)->toBe('Corrected G-DRG selection');
});

it('prevents editing non-rejected claims', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.edit-claims');

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Act
    $response = $this->actingAs($user)
        ->put(route('admin.insurance.claims.update', $claim), [
            'notes' => 'Trying to edit vetted claim',
        ]);

    // Assert
    $response->assertForbidden();
});

it('prevents preparing non-rejected claims for resubmission', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Act
    $response = $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    // Assert - Policy returns 403 for non-rejected claims
    $response->assertForbidden();

    $claim->refresh();
    expect($claim->status)->toBe('vetted'); // Status unchanged
});

dataset('rejection_reasons', [
    'Missing documentation',
    'Invalid G-DRG code',
    'Incorrect patient information',
    'Duplicate claim submission',
    'Service not covered',
]);

it('handles various rejection reasons correctly', function (string $rejectionReason) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.resubmit-claims');

    $claim = InsuranceClaim::factory()->create([
        'status' => 'rejected',
        'rejection_reason' => $rejectionReason,
        'total_claim_amount' => 500.00,
    ]);

    // Act
    $this->actingAs($user)
        ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

    // Assert
    $claim->refresh();
    expect($claim->status)->toBe('vetted');
    expect($claim->notes)->toContain($rejectionReason);
})->with('rejection_reasons');
