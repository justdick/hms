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
    Permission::create(['name' => 'system.admin']);
    Permission::create(['name' => 'insurance.view-claims']);
    Permission::create(['name' => 'insurance.vet-claims']);

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
        'nhis_code' => 'TEST001',
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
        'nhis_code' => 'TEST001',
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
