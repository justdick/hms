<?php

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\InsuranceClaim;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'insurance.view-batches']);
    Permission::firstOrCreate(['name' => 'insurance.manage-batches']);
    Permission::firstOrCreate(['name' => 'insurance.submit-batches']);
    Permission::firstOrCreate(['name' => 'insurance.export-batches']);
    Permission::firstOrCreate(['name' => 'insurance.record-batch-responses']);
});

describe('index', function () {
    it('denies access to unauthorized user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/admin/insurance/batches');

        $response->assertForbidden();
    });

    // Note: Tests for index page rendering are skipped until frontend page is created (Phase 5)
    // The controller and authorization are tested via the authorization test above
});

describe('store', function () {
    it('creates a new claim batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $response = $this->actingAs($user)
            ->post('/admin/insurance/batches', [
                'name' => 'November 2025 Claims',
                'submission_period' => '2025-11-01',
                'notes' => 'Monthly batch submission',
            ]);

        $response->assertRedirect();

        expect(ClaimBatch::count())->toBe(1);
        $batch = ClaimBatch::first();
        expect($batch->name)->toBe('November 2025 Claims')
            ->and($batch->status)->toBe('draft')
            ->and($batch->created_by)->toBe($user->id);
    });

    it('validates required fields', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $response = $this->actingAs($user)
            ->post('/admin/insurance/batches', []);

        $response->assertSessionHasErrors(['name', 'submission_period']);
    });

    it('denies creation to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-batches');

        $response = $this->actingAs($user)
            ->post('/admin/insurance/batches', [
                'name' => 'Test Batch',
                'submission_period' => '2025-11-01',
            ]);

        $response->assertForbidden();
    });
});

describe('show', function () {
    // Note: Tests for show page rendering are skipped until frontend page is created (Phase 5)
    // The controller and authorization are tested via the authorization test below

    it('denies access to unauthorized user', function () {
        $user = User::factory()->create();

        $batch = ClaimBatch::factory()->create();

        $response = $this->actingAs($user)
            ->get("/admin/insurance/batches/{$batch->id}");

        $response->assertForbidden();
    });
});

describe('addClaims', function () {
    it('adds vetted claims to a draft batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/claims", [
                'claim_ids' => [$claim->id],
            ]);

        $response->assertRedirect();
        expect(ClaimBatchItem::count())->toBe(1);
        expect(ClaimBatchItem::first()->insurance_claim_id)->toBe($claim->id);
    });

    it('rejects non-vetted claims', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);
        $claim = InsuranceClaim::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/claims", [
                'claim_ids' => [$claim->id],
            ]);

        $response->assertRedirect();
        expect(ClaimBatchItem::count())->toBe(0);
    });

    it('prevents adding claims to finalized batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/claims", [
                'claim_ids' => [$claim->id],
            ]);

        $response->assertForbidden();
    });

    it('validates claim_ids is required', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/claims", []);

        $response->assertSessionHasErrors('claim_ids');
    });
});

describe('removeClaim', function () {
    it('removes a claim from a draft batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        $response = $this->actingAs($user)
            ->delete("/admin/insurance/batches/{$batch->id}/claims/{$claim->id}");

        $response->assertRedirect();
        expect(ClaimBatchItem::count())->toBe(0);
    });

    it('prevents removing claims from finalized batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        $response = $this->actingAs($user)
            ->delete("/admin/insurance/batches/{$batch->id}/claims/{$claim->id}");

        $response->assertForbidden();
    });
});

describe('finalize', function () {
    it('finalizes a draft batch with claims', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create([
            'status' => 'draft',
            'total_claims' => 1,
        ]);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/finalize");

        $response->assertRedirect();
        $batch->refresh();
        expect($batch->status)->toBe('finalized');
    });

    it('prevents finalizing an empty batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create([
            'status' => 'draft',
            'total_claims' => 0,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/finalize");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $batch->refresh();
        expect($batch->status)->toBe('draft');
    });

    it('prevents finalizing already finalized batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/finalize");

        $response->assertForbidden();
    });
});

describe('markSubmitted', function () {
    it('marks a finalized batch as submitted', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.submit-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/submit", [
                'submitted_at' => '2025-11-26 10:00:00',
            ]);

        $response->assertRedirect();
        $batch->refresh();
        expect($batch->status)->toBe('submitted')
            ->and($batch->submitted_at)->not->toBeNull();
    });

    it('prevents submitting a draft batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.submit-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/submit");

        $response->assertForbidden();
    });

    it('denies submission to unauthorized user', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.manage-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/submit");

        $response->assertForbidden();
    });
});

describe('recordResponse', function () {
    it('records NHIA response for submitted batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.record-batch-responses');

        $batch = ClaimBatch::factory()->create(['status' => 'submitted']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
            'claim_amount' => 100.00,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/response", [
                'responses' => [
                    $claim->id => [
                        'status' => 'approved',
                        'approved_amount' => 95.00,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $batchItem = ClaimBatchItem::first();
        expect($batchItem->status)->toBe('approved')
            ->and((float) $batchItem->approved_amount)->toBe(95.00);
    });

    it('records rejection reason for rejected claims', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.record-batch-responses');

        $batch = ClaimBatch::factory()->create(['status' => 'submitted']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/response", [
                'responses' => [
                    $claim->id => [
                        'status' => 'rejected',
                        'rejection_reason' => 'Invalid diagnosis code',
                    ],
                ],
            ]);

        $response->assertRedirect();

        $batchItem = ClaimBatchItem::first();
        expect($batchItem->status)->toBe('rejected')
            ->and($batchItem->rejection_reason)->toBe('Invalid diagnosis code');
    });

    it('prevents recording response for non-submitted batch', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.record-batch-responses');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/response", [
                'responses' => [
                    $claim->id => [
                        'status' => 'approved',
                    ],
                ],
            ]);

        $response->assertForbidden();
    });

    it('validates responses array is required', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.record-batch-responses');

        $batch = ClaimBatch::factory()->create(['status' => 'submitted']);

        $response = $this->actingAs($user)
            ->post("/admin/insurance/batches/{$batch->id}/response", []);

        $response->assertSessionHasErrors('responses');
    });
});
