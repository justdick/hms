<?php

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions (including system.admin for policy check)
    Permission::firstOrCreate(['name' => 'system.admin']);
    Permission::firstOrCreate(['name' => 'insurance.view-claims']);
    Permission::firstOrCreate(['name' => 'insurance.vet-claims']);

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

    $this->claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'total_claim_amount' => 100.00,
    ]);

    $this->nhisTariff = NhisTariff::factory()->create([
        'name' => 'Test Medicine',
        'category' => 'medicine',
        'price' => 25.00,
        'is_active' => true,
    ]);
});

it('can add an item to a claim from NHIS tariff', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/admin/insurance/claims/{$this->claim->id}/items", [
            'nhis_tariff_id' => $this->nhisTariff->id,
            'quantity' => 2,
        ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'message' => 'Item added to claim successfully.',
    ]);

    // Verify item was created
    $this->assertDatabaseHas('insurance_claim_items', [
        'insurance_claim_id' => $this->claim->id,
        'charge_id' => null, // Manual item has no charge
        'nhis_tariff_id' => $this->nhisTariff->id,
        'nhis_code' => $this->nhisTariff->nhis_code,
        'description' => 'Test Medicine',
        'quantity' => 2,
    ]);
});

it('can remove an item from a claim', function () {
    // Create an item on the claim
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'description' => 'Test Item',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}");

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'message' => 'Item removed from claim successfully.',
    ]);

    // Verify item was deleted
    $this->assertDatabaseMissing('insurance_claim_items', [
        'id' => $item->id,
    ]);
});

it('cannot remove an item that belongs to another claim', function () {
    $otherClaim = InsuranceClaim::factory()->create();
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $otherClaim->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}");

    $response->assertForbidden();

    // Verify item still exists
    $this->assertDatabaseHas('insurance_claim_items', [
        'id' => $item->id,
    ]);
});

it('recalculates claim totals after adding an item', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/admin/insurance/claims/{$this->claim->id}/items", [
            'nhis_tariff_id' => $this->nhisTariff->id,
            'quantity' => 2,
        ]);

    $response->assertSuccessful();

    $this->claim->refresh();

    // Total should include the new item (25.00 * 2 = 50.00)
    // For NHIS claims, only NHIS-priced items are summed
    expect((float) $this->claim->total_claim_amount)->toBe(50.0);
});

it('recalculates claim totals after removing an item', function () {
    // Add an item first
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'nhis_price' => 30.00,
        'quantity' => 1,
        'subtotal' => 30.00,
        'insurance_pays' => 30.00,
    ]);

    $this->claim->update(['total_claim_amount' => 100.00]);
    $initialTotal = $this->claim->total_claim_amount;

    $response = $this->actingAs($this->user)
        ->deleteJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}");

    $response->assertSuccessful();

    $this->claim->refresh();

    // Total should have decreased
    expect((float) $this->claim->total_claim_amount)->toBeLessThan((float) $initialTotal);
});

it('requires permission to add items', function () {
    $unauthorizedUser = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->postJson("/admin/insurance/claims/{$this->claim->id}/items", [
            'nhis_tariff_id' => $this->nhisTariff->id,
            'quantity' => 1,
        ]);

    $response->assertForbidden();
});

it('requires permission to remove items', function () {
    $unauthorizedUser = User::factory()->create();
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
    ]);

    $response = $this->actingAs($unauthorizedUser)
        ->deleteJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}");

    $response->assertForbidden();
});

it('validates nhis_tariff_id is required when adding item', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/admin/insurance/claims/{$this->claim->id}/items", [
            'quantity' => 1,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['nhis_tariff_id']);
});

it('validates quantity is required when adding item', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/admin/insurance/claims/{$this->claim->id}/items", [
            'nhis_tariff_id' => $this->nhisTariff->id,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['quantity']);
});

it('can update item quantity', function () {
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'quantity' => 1,
        'unit_tariff' => 25.00,
        'nhis_price' => 25.00,
        'subtotal' => 25.00,
        'insurance_pays' => 25.00,
        'is_covered' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'quantity' => 3,
        ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'item' => [
            'id' => $item->id,
            'quantity' => 3,
        ],
    ]);

    $item->refresh();
    expect($item->quantity)->toBe(3)
        ->and((float) $item->subtotal)->toBe(75.0);
});

it('can update item frequency', function () {
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'item_type' => 'drug',
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'frequency' => 'TDS',
        ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'item' => [
            'id' => $item->id,
            'frequency' => 'TDS',
        ],
    ]);

    $item->refresh();
    expect($item->frequency)->toBe('TDS');
});

it('can update both quantity and frequency at once', function () {
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'item_type' => 'drug',
        'quantity' => 1,
        'unit_tariff' => 10.00,
        'nhis_price' => 10.00,
        'subtotal' => 10.00,
        'insurance_pays' => 10.00,
        'is_covered' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'quantity' => 5,
            'frequency' => 'Twice daily (BID)',
        ]);

    $response->assertSuccessful();

    $item->refresh();
    expect($item->quantity)->toBe(5)
        ->and($item->frequency)->toBe('Twice daily (BID)')
        ->and((float) $item->subtotal)->toBe(50.0);
});

it('cannot update item belonging to another claim', function () {
    $otherClaim = InsuranceClaim::factory()->create();
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $otherClaim->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'quantity' => 5,
        ]);

    $response->assertForbidden();
});

it('validates quantity minimum when updating item', function () {
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'quantity' => 0,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['quantity']);
});

it('requires permission to update items', function () {
    $unauthorizedUser = User::factory()->create();
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
    ]);

    $response = $this->actingAs($unauthorizedUser)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'quantity' => 2,
        ]);

    $response->assertForbidden();
});

it('recalculates claim totals after updating item quantity', function () {
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'nhis_price' => 20.00,
        'unit_tariff' => 20.00,
        'quantity' => 1,
        'subtotal' => 20.00,
        'insurance_pays' => 20.00,
        'is_covered' => true,
    ]);

    $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'quantity' => 4,
        ]);

    $this->claim->refresh();
    expect((float) $this->claim->total_claim_amount)->toBe(80.0);
});

it('can update item date', function () {
    $item = InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $this->claim->id,
        'item_date' => '2026-01-01',
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/admin/insurance/claims/{$this->claim->id}/items/{$item->id}", [
            'item_date' => '2026-02-15',
        ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'item' => [
            'id' => $item->id,
            'item_date' => '2026-02-15',
        ],
    ]);

    $item->refresh();
    expect($item->item_date->format('Y-m-d'))->toBe('2026-02-15');
});
