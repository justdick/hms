<?php

/**
 * Property-Based Test for Finalized Batch Immutability
 *
 * **Feature: nhis-claims-integration, Property 24: Finalized Batch Immutability**
 * **Validates: Requirements 14.5**
 *
 * Property: For any finalized batch, attempts to add or remove claims should be rejected.
 */

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\InsuranceClaim;
use App\Models\User;
use App\Services\ClaimBatchService;

beforeEach(function () {
    // Clean up
    ClaimBatch::query()->delete();
    InsuranceClaim::query()->delete();
});

dataset('immutable_statuses', [
    ['finalized'],
    ['submitted'],
    ['processing'],
    ['completed'],
]);

it('allows modifications to draft batches', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Draft Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Act - Add claim to draft batch
    $result = $service->addClaimsToBatch($batch, [$claim->id]);

    // Assert
    expect($result['added'])->toBe(1);
    expect($batch->refresh()->total_claims)->toBe(1);

    // Act - Remove claim from draft batch
    $removed = $service->removeClaimFromBatch($batch, $claim);

    // Assert
    expect($removed)->toBeTrue();
    expect($batch->refresh()->total_claims)->toBe(0);
});

it('prevents adding claims to finalized batch', function (string $status) {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    // Create a batch with the given status
    $batch = ClaimBatch::factory()->create([
        'status' => $status,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Act & Assert
    expect(fn () => $service->addClaimsToBatch($batch, [$claim->id]))
        ->toThrow(InvalidArgumentException::class, 'finalized and cannot be modified');
})->with('immutable_statuses');

it('prevents removing claims from finalized batch', function (string $status) {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    // Create a batch with claims already in it
    $batch = ClaimBatch::factory()->create([
        'status' => $status,
        'total_claims' => 1,
        'total_amount' => 500.00,
        'created_by' => $user->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);

    // Add the claim to batch directly (bypassing service validation)
    ClaimBatchItem::create([
        'claim_batch_id' => $batch->id,
        'insurance_claim_id' => $claim->id,
        'claim_amount' => 500.00,
        'status' => 'pending',
    ]);

    // Act & Assert
    expect(fn () => $service->removeClaimFromBatch($batch, $claim))
        ->toThrow(InvalidArgumentException::class, 'finalized and cannot be modified');
})->with('immutable_statuses');

it('finalizes a draft batch successfully', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Add a claim to the batch
    $claim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);
    $service->addClaimsToBatch($batch, [$claim->id]);

    // Act
    $finalizedBatch = $service->finalizeBatch($batch);

    // Assert
    expect($finalizedBatch->status)->toBe('finalized');
    expect($finalizedBatch->isFinalized())->toBeTrue();
    expect($finalizedBatch->canBeModified())->toBeFalse();
});

it('prevents finalizing an already finalized batch', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = ClaimBatch::factory()->finalized()->create([
        'total_claims' => 1,
        'total_amount' => 500.00,
        'created_by' => $user->id,
    ]);

    // Act & Assert
    expect(fn () => $service->finalizeBatch($batch))
        ->toThrow(InvalidArgumentException::class, 'Only draft batches can be finalized');
});

it('prevents finalizing an empty batch', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Empty Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Act & Assert
    expect(fn () => $service->finalizeBatch($batch))
        ->toThrow(InvalidArgumentException::class, 'Cannot finalize an empty batch');
});

it('maintains batch totals after finalization', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Add multiple claims
    $claims = [];
    $totalAmount = 0;
    for ($i = 0; $i < 3; $i++) {
        $amount = fake()->randomFloat(2, 100, 500);
        $claims[] = InsuranceClaim::factory()->create([
            'status' => 'vetted',
            'total_claim_amount' => $amount,
        ]);
        $totalAmount += $amount;
    }

    $service->addClaimsToBatch($batch, collect($claims)->pluck('id')->toArray());

    // Act
    $finalizedBatch = $service->finalizeBatch($batch);

    // Assert
    expect($finalizedBatch->total_claims)->toBe(3);
    expect(round((float) $finalizedBatch->total_amount, 2))->toBe(round($totalAmount, 2));
});

it('preserves batch state across multiple modification attempts on finalized batch', function () {
    // Arrange
    $service = new ClaimBatchService;
    $user = User::factory()->create();

    $batch = $service->createBatch(
        name: 'Test Batch',
        submissionPeriod: now(),
        createdBy: $user
    );

    // Add initial claim and finalize
    $initialClaim = InsuranceClaim::factory()->create([
        'status' => 'vetted',
        'total_claim_amount' => 500.00,
    ]);
    $service->addClaimsToBatch($batch, [$initialClaim->id]);
    $service->finalizeBatch($batch);

    $originalTotalClaims = $batch->refresh()->total_claims;
    $originalTotalAmount = $batch->total_amount;

    // Create new claims to try to add
    $newClaims = InsuranceClaim::factory()->count(3)->create([
        'status' => 'vetted',
        'total_claim_amount' => 200.00,
    ]);

    // Act - Try multiple add attempts
    foreach ($newClaims as $claim) {
        try {
            $service->addClaimsToBatch($batch, [$claim->id]);
        } catch (InvalidArgumentException $e) {
            // Expected
        }
    }

    // Assert - Batch state unchanged
    $batch->refresh();
    expect($batch->total_claims)->toBe($originalTotalClaims);
    expect((float) $batch->total_amount)->toBe((float) $originalTotalAmount);
});
