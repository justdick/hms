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
    // Create permissions (including system.admin for policy check)
    $this->permissions = [
        'system.admin' => Permission::create(['name' => 'system.admin']),
        'insurance.view-claims' => Permission::create(['name' => 'insurance.view-claims']),
        'insurance.vet-claims' => Permission::create(['name' => 'insurance.vet-claims']),
    ];

    // Create user with vet permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['insurance.view-claims', 'insurance.vet-claims']);

    // Create insurance provider, plan, and patient insurance
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create a claim
    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'total_claim_amount' => 1000.00,
    ]);

    // Create claim items
    $this->items = InsuranceClaimItem::factory()->count(3)->create([
        'insurance_claim_id' => $this->claim->id,
        'subtotal' => 300.00,
        'insurance_pays' => 270.00,
        'patient_pays' => 30.00,
        'is_approved' => null,
    ]);
});

it('displays claim details page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.show', $this->claim));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Show')
        ->has('claim')
        ->where('claim.id', $this->claim->id)
        ->where('claim.claim_check_code', $this->claim->claim_check_code)
        ->has('claim.items', 3)
        ->has('can')
        ->where('can.vet', true)
    );
});

it('requires authentication to view claim details', function () {
    $response = $this->get(route('admin.insurance.claims.show', $this->claim));

    $response->assertRedirect(route('login'));
});

it('requires permission to view claim details', function () {
    $unauthorizedUser = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->get(route('admin.insurance.claims.show', $this->claim));

    $response->assertForbidden();
});

it('allows vetting and approving a claim', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'is_approved' => true,
                'rejection_reason' => null,
            ])->toArray(),
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));
    $response->assertSessionHas('success', 'Claim has been vetted and approved.');

    $this->claim->refresh();
    expect($this->claim->status)->toBe('vetted');
    expect($this->claim->vetted_by)->toBe($this->user->id);
    expect($this->claim->vetted_at)->not->toBeNull();
    expect($this->claim->rejection_reason)->toBeNull();

    // Check all items are approved
    foreach ($this->items as $item) {
        $item->refresh();
        expect($item->is_approved)->toBeTrue();
    }
});

it('allows rejecting a claim with reason', function () {
    $rejectionReason = 'Claim does not meet coverage criteria';

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'reject',
            'rejection_reason' => $rejectionReason,
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));
    $response->assertSessionHas('success', 'Claim has been rejected.');

    $this->claim->refresh();
    expect($this->claim->status)->toBe('rejected');
    expect($this->claim->vetted_by)->toBe($this->user->id);
    expect($this->claim->vetted_at)->not->toBeNull();
    expect($this->claim->rejection_reason)->toBe($rejectionReason);
});

it('requires rejection reason when rejecting a claim', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'reject',
            'rejection_reason' => '',
        ]);

    $response->assertSessionHasErrors(['rejection_reason']);
});

it('allows partial approval of claim items', function () {
    $itemsData = [
        [
            'id' => $this->items[0]->id,
            'is_approved' => true,
            'rejection_reason' => null,
        ],
        [
            'id' => $this->items[1]->id,
            'is_approved' => false,
            'rejection_reason' => 'Not covered by policy',
        ],
        [
            'id' => $this->items[2]->id,
            'is_approved' => true,
            'rejection_reason' => null,
        ],
    ];

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
            'items' => $itemsData,
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));

    // Check first item is approved
    $this->items[0]->refresh();
    expect($this->items[0]->is_approved)->toBeTrue();
    expect($this->items[0]->rejection_reason)->toBeNull();

    // Check second item is rejected
    $this->items[1]->refresh();
    expect($this->items[1]->is_approved)->toBeFalse();
    expect($this->items[1]->rejection_reason)->toBe('Not covered by policy');

    // Check third item is approved
    $this->items[2]->refresh();
    expect($this->items[2]->is_approved)->toBeTrue();
    expect($this->items[2]->rejection_reason)->toBeNull();
});

it('recalculates approved amount based on approved items', function () {
    $itemsData = [
        [
            'id' => $this->items[0]->id,
            'is_approved' => true,
            'rejection_reason' => null,
        ],
        [
            'id' => $this->items[1]->id,
            'is_approved' => false,
            'rejection_reason' => 'Not covered',
        ],
        [
            'id' => $this->items[2]->id,
            'is_approved' => true,
            'rejection_reason' => null,
        ],
    ];

    $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
            'items' => $itemsData,
        ]);

    $this->claim->refresh();

    // Should be 2 items * 270.00 = 540.00
    expect($this->claim->approved_amount)->toBe('540.00');
    expect($this->claim->insurance_covered_amount)->toBe('540.00');
    // Total is 1000, approved is 540, so copay is 460
    expect($this->claim->patient_copay_amount)->toBe('460.00');
});

it('prevents vetting of already vetted claims', function () {
    $this->claim->update(['status' => 'vetted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
        ]);

    $response->assertForbidden();
});

it('prevents vetting of submitted claims', function () {
    $this->claim->update(['status' => 'submitted']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
        ]);

    $response->assertForbidden();
});

it('allows vetting draft claims', function () {
    $this->claim->update(['status' => 'draft']);

    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
        ]);

    $response->assertRedirect(route('admin.insurance.claims.show', $this->claim));

    $this->claim->refresh();
    expect($this->claim->status)->toBe('vetted');
});

it('requires permission to vet claims', function () {
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->givePermissionTo('insurance.view-claims');

    $response = $this->actingAs($unauthorizedUser)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
        ]);

    $response->assertForbidden();
});

it('validates action parameter is required', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            // Missing action
        ]);

    $response->assertSessionHasErrors(['action']);
});

it('validates action parameter must be approve or reject', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'invalid_action',
        ]);

    $response->assertSessionHasErrors(['action']);
});

it('validates item IDs must exist', function () {
    $response = $this->actingAs($this->user)
        ->post(route('admin.insurance.claims.vet', $this->claim), [
            'action' => 'approve',
            'items' => [
                [
                    'id' => 99999, // Non-existent ID
                    'is_approved' => true,
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['items.0.id']);
});

it('displays vetting metadata correctly', function () {
    $vettedBy = User::factory()->create(['name' => 'Dr. Vetter']);
    $this->claim->update([
        'status' => 'vetted',
        'vetted_by' => $vettedBy->id,
        'vetted_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.claims.show', $this->claim));

    $response->assertInertia(fn ($page) => $page
        ->where('claim.status', 'vetted')
        ->where('claim.vetted_by_user.id', $vettedBy->id)
        ->where('claim.vetted_by_user.name', 'Dr. Vetter')
        ->has('claim.vetted_at')
    );
});
