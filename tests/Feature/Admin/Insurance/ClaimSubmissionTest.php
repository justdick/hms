<?php

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    $this->permissions = [
        'system.admin' => Permission::create(['name' => 'system.admin']),
        'insurance.view-claims' => Permission::create(['name' => 'insurance.view-claims']),
        'insurance.submit-claims' => Permission::create(['name' => 'insurance.submit-claims']),
        'insurance.record-payments' => Permission::create(['name' => 'insurance.record-payments']),
        'insurance.reject-claims' => Permission::create(['name' => 'insurance.reject-claims']),
        'insurance.resubmit-claims' => Permission::create(['name' => 'insurance.resubmit-claims']),
        'insurance.export-claims' => Permission::create(['name' => 'insurance.export-claims']),
    ];

    // Create user with submission permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'insurance.view-claims',
        'insurance.submit-claims',
        'insurance.record-payments',
        'insurance.reject-claims',
        'insurance.resubmit-claims',
        'insurance.export-claims',
    ]);

    // Create insurance provider, plan, and patient insurance
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create a vetted claim ready for submission
    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'vetted',
        'total_claim_amount' => 1000.00,
        'approved_amount' => 900.00,
    ]);

    // Create claim items
    $this->items = InsuranceClaimItem::factory()->count(3)->create([
        'insurance_claim_id' => $this->claim->id,
        'subtotal' => 300.00,
        'insurance_pays' => 270.00,
        'patient_pays' => 30.00,
        'is_approved' => true,
    ]);
});

// ============================================================================
// Single Claim Submission Tests
// ============================================================================

it('allows submitting a single vetted claim', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_id' => $this->claim->id,
            'submission_date' => now()->toDateString(),
            'notes' => 'Submitting claim for processing',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->claim->refresh();
    expect($this->claim->status)->toBe('submitted')
        ->and($this->claim->submitted_by)->toBe($this->user->id)
        ->and($this->claim->submitted_at)->not->toBeNull()
        ->and($this->claim->submission_date->toDateString())->toBe(now()->toDateString())
        ->and($this->claim->notes)->toBe('Submitting claim for processing');
});

it('requires authentication to submit a claim', function () {
    $response = $this->post(route('admin.insurance.claims.submit'), [
        'claim_id' => $this->claim->id,
    ]);

    $response->assertRedirect(route('login'));
});

it('requires permission to submit a claim', function () {
    $unauthorizedUser = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_id' => $this->claim->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

it('only allows submitting vetted claims', function () {
    $draftClaim = InsuranceClaim::factory()->create([
        'patient_id' => $this->claim->patient_id,
        'patient_insurance_id' => $this->claim->patient_insurance_id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_id' => $draftClaim->id,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $draftClaim->refresh();
    expect($draftClaim->status)->toBe('draft');
});

it('validates submission date is not in future', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_id' => $this->claim->id,
            'submission_date' => now()->addDay()->toDateString(),
        ]);

    $response->assertSessionHasErrors('submission_date');
});

// ============================================================================
// Batch Submission Tests
// ============================================================================

it('allows batch submission of multiple claims', function () {
    $claim2 = InsuranceClaim::factory()->create([
        'patient_id' => $this->claim->patient_id,
        'patient_insurance_id' => $this->claim->patient_insurance_id,
        'status' => 'vetted',
    ]);

    $claim3 = InsuranceClaim::factory()->create([
        'patient_id' => $this->claim->patient_id,
        'patient_insurance_id' => $this->claim->patient_insurance_id,
        'status' => 'vetted',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_ids' => [$this->claim->id, $claim2->id, $claim3->id],
            'batch_reference' => 'BATCH-TEST-001',
            'submission_date' => now()->toDateString(),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->claim->refresh();
    $claim2->refresh();
    $claim3->refresh();

    expect($this->claim->status)->toBe('submitted')
        ->and($this->claim->batch_reference)->toBe('BATCH-TEST-001')
        ->and($this->claim->batch_submitted_at)->not->toBeNull()
        ->and($claim2->status)->toBe('submitted')
        ->and($claim2->batch_reference)->toBe('BATCH-TEST-001')
        ->and($claim3->status)->toBe('submitted')
        ->and($claim3->batch_reference)->toBe('BATCH-TEST-001');
});

it('generates batch reference automatically if not provided', function () {
    $claim2 = InsuranceClaim::factory()->create([
        'patient_id' => $this->claim->patient_id,
        'patient_insurance_id' => $this->claim->patient_insurance_id,
        'status' => 'vetted',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_ids' => [$this->claim->id, $claim2->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->claim->refresh();
    expect($this->claim->batch_reference)->not->toBeNull()
        ->and($this->claim->batch_reference)->toContain('BATCH-');
});

it('validates at least one claim for batch submission', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_ids' => [],
        ]);

    $response->assertSessionHasErrors('claim_ids');
});

// ============================================================================
// Payment Recording Tests
// ============================================================================

it('allows recording payment for submitted claim', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_date' => now()->toDateString(),
            'payment_amount' => 900.00,
            'payment_reference' => 'PAY-12345',
            'approval_date' => now()->subDay()->toDateString(),
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));
    $response->assertSessionHas('success', 'Payment recorded successfully.');

    $this->claim->refresh();
    expect($this->claim->status)->toBe('paid')
        ->and($this->claim->payment_date->toDateString())->toBe(now()->toDateString())
        ->and((float) $this->claim->payment_amount)->toBe(900.00)
        ->and($this->claim->payment_reference)->toBe('PAY-12345')
        ->and($this->claim->payment_recorded_by)->toBe($this->user->id)
        ->and($this->claim->approved_by)->toBe($this->user->id);
});

it('requires payment date for payment recording', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_amount' => 900.00,
            'payment_reference' => 'PAY-12345',
        ]);

    $response->assertSessionHasErrors('payment_date');
});

it('requires payment amount for payment recording', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_date' => now()->toDateString(),
            'payment_reference' => 'PAY-12345',
        ]);

    $response->assertSessionHasErrors('payment_amount');
});

it('requires payment reference for payment recording', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_date' => now()->toDateString(),
            'payment_amount' => 900.00,
        ]);

    $response->assertSessionHasErrors('payment_reference');
});

it('validates payment amount is positive', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_date' => now()->toDateString(),
            'payment_amount' => -100.00,
            'payment_reference' => 'PAY-12345',
        ]);

    $response->assertSessionHasErrors('payment_amount');
});

it('only allows recording payment for submitted or approved claims', function () {
    $this->claim->update(['status' => 'draft']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_date' => now()->toDateString(),
            'payment_amount' => 900.00,
            'payment_reference' => 'PAY-12345',
        ]);

    $response->assertForbidden();
});

// ============================================================================
// Rejection Handling Tests
// ============================================================================

it('allows marking a submitted claim as rejected', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-rejected', $this->claim), [
            'rejection_reason' => 'Incomplete documentation provided',
            'rejection_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));
    $response->assertSessionHas('success', 'Claim marked as rejected.');

    $this->claim->refresh();
    expect($this->claim->status)->toBe('rejected')
        ->and($this->claim->rejection_reason)->toBe('Incomplete documentation provided')
        ->and($this->claim->rejected_by)->toBe($this->user->id)
        ->and($this->claim->rejected_at)->not->toBeNull();
});

it('requires rejection reason', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-rejected', $this->claim), [
            'rejection_reason' => '',
        ]);

    $response->assertSessionHasErrors('rejection_reason');
});

it('validates rejection reason has minimum length', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-rejected', $this->claim), [
            'rejection_reason' => 'Short',
        ]);

    $response->assertSessionHasErrors('rejection_reason');
});

// ============================================================================
// Resubmission Tests
// ============================================================================

it('allows resubmitting a rejected claim', function () {
    $this->claim->update([
        'status' => 'rejected',
        'rejection_reason' => 'Incomplete documentation',
        'rejected_by' => $this->user->id,
        'rejected_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.resubmit', $this->claim), [
            'submission_date' => now()->toDateString(),
            'notes' => 'Resubmitting with complete documentation',
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));
    $response->assertSessionHas('success', 'Claim resubmitted successfully.');

    $this->claim->refresh();
    expect($this->claim->status)->toBe('submitted')
        ->and($this->claim->resubmission_count)->toBe(1)
        ->and($this->claim->last_resubmitted_at)->not->toBeNull()
        ->and($this->claim->rejection_reason)->toBeNull()
        ->and($this->claim->rejected_by)->toBeNull()
        ->and($this->claim->rejected_at)->toBeNull();
});

it('increments resubmission count on each resubmission', function () {
    $this->claim->update([
        'status' => 'rejected',
        'resubmission_count' => 2,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.resubmit', $this->claim), [
            'submission_date' => now()->toDateString(),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->claim->refresh();
    expect($this->claim->resubmission_count)->toBe(3);
});

it('only allows resubmitting rejected or draft claims', function () {
    $this->claim->update(['status' => 'paid']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.resubmit', $this->claim), [
            'submission_date' => now()->toDateString(),
        ]);

    $response->assertForbidden();
});

// ============================================================================
// Export Tests
// ============================================================================

it('allows exporting claims to CSV', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.export', [
            'format' => 'excel',
        ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('insurance-claims-');
});

it('allows filtering exported claims by status', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->claim->patient_id,
        'patient_insurance_id' => $this->claim->patient_insurance_id,
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.export', [
            'format' => 'excel',
            'status' => 'submitted',
        ]));

    $response->assertSuccessful();
});

it('allows filtering exported claims by date range', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.export', [
            'format' => 'excel',
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

    $response->assertSuccessful();
});

it('requires permission to export claims', function () {
    $unauthorizedUser = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->get(route('admin.insurance.claims.export'));

    $response->assertForbidden();
});

// ============================================================================
// Status Transition Tests
// ============================================================================

it('ensures proper status transition from vetted to submitted', function () {
    expect($this->claim->status)->toBe('vetted');

    $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.submit'), [
            'claim_id' => $this->claim->id,
        ]);

    $this->claim->refresh();
    expect($this->claim->status)->toBe('submitted');
});

it('ensures proper status transition from submitted to paid', function () {
    $this->claim->update(['status' => 'submitted']);

    $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-paid', $this->claim), [
            'payment_date' => now()->toDateString(),
            'payment_amount' => 900.00,
            'payment_reference' => 'PAY-12345',
        ]);

    $this->claim->refresh();
    expect($this->claim->status)->toBe('paid');
});

it('ensures proper status transition from submitted to rejected', function () {
    $this->claim->update(['status' => 'submitted']);

    $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.mark-rejected', $this->claim), [
            'rejection_reason' => 'Insufficient documentation provided',
        ]);

    $this->claim->refresh();
    expect($this->claim->status)->toBe('rejected');
});

it('ensures proper status transition from rejected to submitted on resubmission', function () {
    $this->claim->update(['status' => 'rejected']);

    $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.resubmit', $this->claim), [
            'submission_date' => now()->toDateString(),
        ]);

    $this->claim->refresh();
    expect($this->claim->status)->toBe('submitted');
});
