<?php

/**
 * Property-Based Test for Batch Only Accepts Vetted Claims
 *
 * **Feature: nhis-claims-integration, Property 23: Batch Only Accepts Vetted Claims**
 * **Validates: Requirements 14.2**
 *
 * Property: For any attempt to add a claim to a batch, only claims with status "vetted"
 * should be accepted.
 */

use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use App\Models\User;
use App\Services\ClaimBatchService;

beforeEach(function () {
    // Clean up
    ClaimBatch::query()->delete();
    InsuranceClaim::query()->delete();
});

dataset('non_vetted_statuses', [
    ['draft'],
    ['pending_vetting'],
    ['submitted'],
    ['approved'],
    ['rejected'],
    ['paid'],
]);

dataset('claim_counts', [
    [1],
    [3],
    [5],
]);

it('accepts vetted claims into a batch', function (int $count) {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Create vetted claims
    $vettedClaims = [];
    for ($i = 0; $i < $count; $i++) {
        $vettedClaims[] = InsuranceClaim::factory()->create([
            'status' => 'vetted',
            'total_claim_amount' => fake()->randomFloat(2, 100, 1000),
        ]);
    }

    $claimIds = collect($vettedClaims)->pluck('id')->toArray();

    // Act
    $result = $service->addClaimsToBatch($batch, $claimIds);

    // Assert
    expect($result['added'])->toBe($count);
    expect($result['skipped'])->toBe(0);
    expect($result['errors'])->toBeEmpty();

    // Verify all claims were added
    $batch->refresh();
    expect($batch->total_claims)->toBe($count);
})->with('claim_counts');

it('rejects non-vetted claims from being added to a batch', function (string $status) {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Create a claim with non-vetted status
    $claim = InsuranceClaim::factory()->create([
        'status' => $status,
        'total_claim_amount' => 500.00,
    ]);

    // Act
    $result = $service->addClaimsToBatch($batch, [$claim->id]);

    // Assert
    expect($result['added'])->toBe(0);
    expect($result['skipped'])->toBe(1);
    expect($result['errors'])->not->toBeEmpty();
    expect($result['errors'][0])->toContain('not vetted');

    // Verify claim was not added
    $batch->refresh();
    expect($batch->total_claims)->toBe(0);
})->with('non_vetted_statuses');

it('handles mixed batch of vetted and non-vetted claims', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Create mix of claims
    $vettedClaim1 = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 100.00,
    ]);
    $vettedClaim2 = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 200.00,
    ]);
    $draftClaim = InsuranceClaim::factory()->create([
        'status' => 'draft',
        'total_claim_amount' => 300.00,
    ]);
    $pendingClaim = InsuranceClaim::factory()->create([
        'status' => 'pending_vetting',
        'total_claim_amount' => 400.00,
    ]);

    $claimIds = [
        $vettedClaim1->id,
        $draftClaim->id,
        $vettedClaim2->id,
        $pendingClaim->id,
    ];

    // Act
    $result = $service->addClaimsToBatch($batch, $claimIds);

    // Assert
    expect($result['added'])->toBe(2); // Only vetted claims
    expect($result['skipped'])->toBe(2); // Non-vetted claims
    expect($result['errors'])->toHaveCount(2);

    // Verify only vetted claims were added
    $batch->refresh();
    expect($batch->total_claims)->toBe(2);
    expect((float) $batch->total_amount)->toBe(300.00); // 100 + 200
});

it('prevents adding duplicate claims to a batch', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Add claim first time
    $service->addClaimsToBatch($batch, [$claim->id]);

    // Act - Try to add same claim again
    $result = $service->addClaimsToBatch($batch, [$claim->id]);

    // Assert
    expect($result['added'])->toBe(0);
    expect($result['skipped'])->toBe(1);
    expect($result['errors'][0])->toContain('already in this batch');

    // Verify only one instance exists
    $batch->refresh();
    expect($batch->total_claims)->toBe(1);
});

it('handles non-existent claim IDs gracefully', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    $nonExistentId = 99999;

    // Act
    $result = $service->addClaimsToBatch($batch, [$nonExistentId]);

    // Assert
    expect($result['added'])->toBe(0);
    expect($result['skipped'])->toBe(1);
    expect($result['errors'][0])->toContain('not found');
});
