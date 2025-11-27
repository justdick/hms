<?php

/**
 * Feature Test for Rejected Claim Resubmission Workflow
 *
 * Tests the complete workflow for correcting and resubmitting rejected claims.
 *
 * _Requirements: 17.5_
 */

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\User;
use App\Services\ClaimBatchService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'insurance.resubmit-claims']);
    Permission::firstOrCreate(['name' => 'insurance.edit-claims']);
    Permission::firstOrCreate(['name' => 'insurance.view-claims']);
    Permission::firstOrCreate(['name' => 'system.admin']);

    // Clean up
    ClaimBatchItem::query()->delete();
    ClaimBatch::query()->delete();
    InsuranceClaimItem::query()->delete();
    InsuranceClaim::query()->delete();
});

describe('Rejected Claim Editing', function () {
    it('allows updating G-DRG on rejected claim', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.edit-claims');

        $oldGdrg = GdrgTariff::factory()->create(['tariff_price' => 100.00]);
        $newGdrg = GdrgTariff::factory()->create(['tariff_price' => 250.00]);

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'Wrong G-DRG selected',
            'gdrg_tariff_id' => $oldGdrg->id,
            'gdrg_amount' => 100.00,
            'total_claim_amount' => 100.00,
        ]);

        $response = $this->actingAs($user)
            ->put(route('admin.insurance.claims.update', $claim), [
                'gdrg_tariff_id' => $newGdrg->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $claim->refresh();
        expect($claim->gdrg_tariff_id)->toBe($newGdrg->id);
        expect((float) $claim->gdrg_amount)->toBe(250.00);
    });

    it('allows updating notes on rejected claim', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.edit-claims');

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'Documentation issue',
            'notes' => null,
        ]);

        $response = $this->actingAs($user)
            ->put(route('admin.insurance.claims.update', $claim), [
                'notes' => 'Corrected documentation attached',
            ]);

        $response->assertRedirect();
        $claim->refresh();
        expect($claim->notes)->toBe('Corrected documentation attached');
    });

    it('recalculates total when G-DRG is updated', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.edit-claims');

        $newGdrg = GdrgTariff::factory()->create(['tariff_price' => 300.00]);

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'gdrg_tariff_id' => null,
            'gdrg_amount' => null,
            'total_claim_amount' => 200.00,
        ]);

        // Add some claim items
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'insurance_pays' => 150.00,
        ]);
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'insurance_pays' => 50.00,
        ]);

        $response = $this->actingAs($user)
            ->put(route('admin.insurance.claims.update', $claim), [
                'gdrg_tariff_id' => $newGdrg->id,
            ]);

        $response->assertRedirect();
        $claim->refresh();

        // Total should be G-DRG (300) + items (150 + 50) = 500
        expect((float) $claim->total_claim_amount)->toBe(500.00);
    });
});

describe('Prepare for Resubmission', function () {
    it('resets claim status to vetted', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.resubmit-claims');

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'Invalid claim',
        ]);

        $response = $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $claim->refresh();
        expect($claim->status)->toBe('vetted');
    });

    it('clears all rejection and submission data', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.resubmit-claims');

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'Rejected by NHIA',
            'rejected_by' => $user->id,
            'rejected_at' => now()->subDay(),
            'submitted_by' => $user->id,
            'submitted_at' => now()->subDays(3),
            'submission_date' => now()->subDays(3)->toDateString(),
            'batch_reference' => 'BATCH-202511-0001',
            'batch_submitted_at' => now()->subDays(3),
        ]);

        $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

        $claim->refresh();

        expect($claim->rejection_reason)->toBeNull();
        expect($claim->rejected_by)->toBeNull();
        expect($claim->rejected_at)->toBeNull();
        expect($claim->submitted_by)->toBeNull();
        expect($claim->submitted_at)->toBeNull();
        expect($claim->submission_date)->toBeNull();
        expect($claim->batch_reference)->toBeNull();
        expect($claim->batch_submitted_at)->toBeNull();
    });

    it('tracks resubmission count and timestamp', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.resubmit-claims');

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'resubmission_count' => 2,
            'last_resubmitted_at' => now()->subMonth(),
        ]);

        $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

        $claim->refresh();

        expect($claim->resubmission_count)->toBe(3);
        expect($claim->last_resubmitted_at->isToday())->toBeTrue();
    });
});

describe('Complete Resubmission Workflow', function () {
    it('allows full workflow: reject -> edit -> prepare -> add to batch', function () {
        $service = new ClaimBatchService;
        $user = User::factory()->create();
        $user->givePermissionTo(['insurance.resubmit-claims', 'insurance.edit-claims']);

        $newGdrg = GdrgTariff::factory()->create(['tariff_price' => 400.00]);

        // Step 1: Create a rejected claim
        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'G-DRG code not appropriate for diagnosis',
            'gdrg_tariff_id' => null,
            'total_claim_amount' => 300.00,
        ]);

        // Step 2: Edit the claim to correct the G-DRG
        $this->actingAs($user)
            ->put(route('admin.insurance.claims.update', $claim), [
                'gdrg_tariff_id' => $newGdrg->id,
                'notes' => 'Corrected G-DRG selection',
            ]);

        $claim->refresh();
        expect($claim->gdrg_tariff_id)->toBe($newGdrg->id);
        expect($claim->status)->toBe('rejected'); // Still rejected

        // Step 3: Prepare for resubmission
        $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

        $claim->refresh();
        expect($claim->status)->toBe('vetted');

        // Step 4: Add to a new batch
        $batch = $service->createBatch(
            name: 'Resubmission Batch November',
            submissionPeriod: now(),
            createdBy: $user
        );

        $result = $service->addClaimsToBatch($batch, [$claim->id]);

        expect($result['added'])->toBe(1);
        expect($result['errors'])->toBeEmpty();

        $batch->refresh();
        expect($batch->total_claims)->toBe(1);
    });

    it('preserves claim data through resubmission workflow', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.resubmit-claims');

        $gdrg = GdrgTariff::factory()->create(['tariff_price' => 500.00]);

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'Minor documentation issue',
            'patient_surname' => 'Smith',
            'patient_other_names' => 'John',
            'membership_id' => 'NHIS-12345',
            'gdrg_tariff_id' => $gdrg->id,
            'gdrg_amount' => 500.00,
            'total_claim_amount' => 750.00,
        ]);

        // Add claim items
        $item = InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'description' => 'Lab Test',
            'insurance_pays' => 250.00,
        ]);

        $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

        $claim->refresh();

        // Verify patient data preserved
        expect($claim->patient_surname)->toBe('Smith');
        expect($claim->patient_other_names)->toBe('John');
        expect($claim->membership_id)->toBe('NHIS-12345');

        // Verify G-DRG preserved
        expect($claim->gdrg_tariff_id)->toBe($gdrg->id);
        expect((float) $claim->gdrg_amount)->toBe(500.00);

        // Verify items preserved
        expect($claim->items()->count())->toBe(1);
        expect($claim->items()->first()->description)->toBe('Lab Test');
    });
});

describe('Authorization', function () {
    it('requires insurance.edit-claims permission to update', function () {
        $user = User::factory()->create();
        // No permission given

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($user)
            ->put(route('admin.insurance.claims.update', $claim), [
                'notes' => 'Test',
            ]);

        $response->assertForbidden();
    });

    it('requires insurance.resubmit-claims permission to prepare', function () {
        $user = User::factory()->create();
        // No permission given

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));

        $response->assertForbidden();
    });

    it('allows system admin to perform all operations', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('system.admin');

        $claim = InsuranceClaim::factory()->create([
            'status' => 'rejected',
            'rejection_reason' => 'Test rejection',
        ]);

        // Update
        $response = $this->actingAs($user)
            ->put(route('admin.insurance.claims.update', $claim), [
                'notes' => 'Admin update',
            ]);
        $response->assertRedirect();

        // Prepare for resubmission
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.claims.prepare-resubmission', $claim));
        $response->assertRedirect();

        $claim->refresh();
        expect($claim->status)->toBe('vetted');
    });
});
