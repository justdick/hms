<?php

use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\InsuranceTariff;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);
});

it('displays tariffs index page', function () {
    InsuranceTariff::factory()->count(3)->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    $response = $this->get(route('admin.insurance.tariffs.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Tariffs/Index')
        ->has('tariffs.data', 3)
        ->has('plans')
    );
});

it('filters tariffs by plan', function () {
    $anotherPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    InsuranceTariff::factory()->count(2)->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    InsuranceTariff::factory()->create([
        'insurance_plan_id' => $anotherPlan->id,
    ]);

    $response = $this->get(route('admin.insurance.tariffs.index', [
        'plan_id' => $this->plan->id,
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('tariffs.data', 2)
    );
});

it('creates a new tariff', function () {
    $data = [
        'insurance_plan_id' => $this->plan->id,
        'item_type' => 'drug',
        'item_code' => 'DRUG001',
        'item_description' => 'Paracetamol 500mg',
        'standard_price' => 100.00,
        'insurance_tariff' => 80.00,
        'effective_from' => '2025-01-01',
    ];

    $response = $this->post(route('admin.insurance.tariffs.store'), $data);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance tariff created successfully.');

    $this->assertDatabaseHas('insurance_tariffs', [
        'item_code' => 'DRUG001',
        'standard_price' => 100.00,
        'insurance_tariff' => 80.00,
    ]);
});

it('validates required fields when creating tariff', function () {
    $response = $this->post(route('admin.insurance.tariffs.store'), []);

    $response->assertSessionHasErrors([
        'insurance_plan_id',
        'item_type',
        'item_code',
        'standard_price',
        'insurance_tariff',
        'effective_from',
    ]);
});

it('validates item type enum', function () {
    $response = $this->post(route('admin.insurance.tariffs.store'), [
        'insurance_plan_id' => $this->plan->id,
        'item_type' => 'invalid_type',
        'item_code' => 'TEST001',
        'standard_price' => 100.00,
        'insurance_tariff' => 80.00,
        'effective_from' => '2025-01-01',
    ]);

    $response->assertSessionHasErrors(['item_type']);
});

it('creates tariff with discount', function () {
    $data = [
        'insurance_plan_id' => $this->plan->id,
        'item_type' => 'service',
        'item_code' => 'SVC001',
        'item_description' => 'X-Ray Chest',
        'standard_price' => 5000.00,
        'insurance_tariff' => 4000.00,
        'effective_from' => '2025-01-01',
    ];

    $response = $this->post(route('admin.insurance.tariffs.store'), $data);

    $response->assertRedirect();

    $tariff = InsuranceTariff::where('item_code', 'SVC001')->first();

    expect($tariff->insurance_tariff)->toBe('4000.00');
    expect($tariff->insurance_tariff)->toBeLessThan($tariff->standard_price);
});

it('updates a tariff', function () {
    $tariff = InsuranceTariff::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'insurance_tariff' => 100.00,
    ]);

    $response = $this->put(route('admin.insurance.tariffs.update', $tariff), [
        'insurance_plan_id' => $this->plan->id,
        'item_type' => $tariff->item_type,
        'item_code' => $tariff->item_code,
        'standard_price' => $tariff->standard_price,
        'insurance_tariff' => 120.00,
        'effective_from' => $tariff->effective_from->toDateString(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance tariff updated successfully.');

    $this->assertDatabaseHas('insurance_tariffs', [
        'id' => $tariff->id,
        'insurance_tariff' => 120.00,
    ]);
});

it('deletes a tariff', function () {
    $tariff = InsuranceTariff::factory()->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    $response = $this->delete(route('admin.insurance.tariffs.destroy', $tariff));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance tariff deleted successfully.');

    $this->assertDatabaseMissing('insurance_tariffs', ['id' => $tariff->id]);
});
