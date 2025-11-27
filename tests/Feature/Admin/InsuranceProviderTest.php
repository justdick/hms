<?php

use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('displays insurance providers index page', function () {
    $providers = InsuranceProvider::factory()->count(3)->create();

    $response = $this->get(route('admin.insurance.providers.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Providers/Index')
        ->has('providers.data', 3)
    );
});

it('displays create insurance provider page', function () {
    $response = $this->get(route('admin.insurance.providers.create'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Providers/Create')
    );
});

it('creates a new insurance provider', function () {
    $data = [
        'name' => 'Test Insurance Co.',
        'code' => 'TEST001',
        'contact_person' => 'John Doe',
        'phone' => '0700000000',
        'email' => 'test@insurance.com',
        'address' => '123 Test Street',
        'claim_submission_method' => 'online',
        'payment_terms_days' => 30,
        'is_active' => true,
        'notes' => 'Test notes',
    ];

    $response = $this->post(route('admin.insurance.providers.store'), $data);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance provider created successfully.');

    $this->assertDatabaseHas('insurance_providers', [
        'name' => 'Test Insurance Co.',
        'code' => 'TEST001',
        'email' => 'test@insurance.com',
    ]);
});

it('validates required fields when creating provider', function () {
    $response = $this->post(route('admin.insurance.providers.store'), []);

    $response->assertSessionHasErrors(['name', 'code', 'claim_submission_method']);
});

it('validates unique provider code', function () {
    $existing = InsuranceProvider::factory()->create(['code' => 'DUPLICATE']);

    $response = $this->post(route('admin.insurance.providers.store'), [
        'name' => 'New Provider',
        'code' => 'DUPLICATE',
        'claim_submission_method' => 'manual',
    ]);

    $response->assertSessionHasErrors(['code']);
});

it('displays insurance provider show page', function () {
    $provider = InsuranceProvider::factory()
        ->has(InsurancePlan::factory()->count(2), 'plans')
        ->create();

    $response = $this->get(route('admin.insurance.providers.show', $provider));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Providers/Show')
        ->has('provider')
        ->where('provider.id', $provider->id)
        ->where('provider.name', $provider->name)
    );
});

it('displays edit insurance provider page', function () {
    $provider = InsuranceProvider::factory()->create();

    $response = $this->get(route('admin.insurance.providers.edit', $provider));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Providers/Edit')
        ->has('provider')
        ->where('provider.data.id', $provider->id)
    );
});

it('updates an insurance provider', function () {
    $provider = InsuranceProvider::factory()->create([
        'name' => 'Original Name',
        'code' => 'ORIG001',
    ]);

    $response = $this->put(route('admin.insurance.providers.update', $provider), [
        'name' => 'Updated Name',
        'code' => 'ORIG001',
        'contact_person' => 'Jane Smith',
        'phone' => '0711111111',
        'email' => 'updated@insurance.com',
        'address' => '456 Updated Street',
        'claim_submission_method' => 'api',
        'payment_terms_days' => 45,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance provider updated successfully.');

    $this->assertDatabaseHas('insurance_providers', [
        'id' => $provider->id,
        'name' => 'Updated Name',
        'email' => 'updated@insurance.com',
    ]);
});

it('deletes an insurance provider without plans', function () {
    $provider = InsuranceProvider::factory()->create();

    $response = $this->delete(route('admin.insurance.providers.destroy', $provider));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance provider deleted successfully.');

    $this->assertDatabaseMissing('insurance_providers', ['id' => $provider->id]);
});

it('prevents deleting provider with existing plans', function () {
    $provider = InsuranceProvider::factory()
        ->has(InsurancePlan::factory()->count(1), 'plans')
        ->create();

    $response = $this->delete(route('admin.insurance.providers.destroy', $provider));

    $response->assertSessionHas('error', 'Cannot delete provider with existing plans.');

    $this->assertDatabaseHas('insurance_providers', ['id' => $provider->id]);
});

it('requires authentication to access provider routes', function () {
    auth()->logout();

    $response = $this->get(route('admin.insurance.providers.index'));

    $response->assertRedirect(route('login'));
});

// NHIS Provider Configuration Tests

it('creates an NHIS provider with is_nhis flag', function () {
    $data = [
        'name' => 'National Health Insurance Scheme',
        'code' => 'NHIS',
        'contact_person' => 'NHIS Admin',
        'phone' => '0800000000',
        'email' => 'info@nhis.gov.gh',
        'address' => 'NHIS Headquarters',
        'claim_submission_method' => 'online',
        'payment_terms_days' => 60,
        'is_nhis' => true,
        'notes' => 'National Health Insurance Scheme provider',
    ];

    $response = $this->post(route('admin.insurance.providers.store'), $data);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('insurance_providers', [
        'name' => 'National Health Insurance Scheme',
        'code' => 'NHIS',
        'is_nhis' => true,
    ]);
});

it('creates a non-NHIS provider with is_nhis flag set to false', function () {
    $data = [
        'name' => 'Private Insurance Co.',
        'code' => 'PRIV001',
        'claim_submission_method' => 'manual',
        'is_nhis' => false,
    ];

    $response = $this->post(route('admin.insurance.providers.store'), $data);

    $response->assertRedirect();

    $this->assertDatabaseHas('insurance_providers', [
        'code' => 'PRIV001',
        'is_nhis' => false,
    ]);
});

it('updates provider to set is_nhis flag', function () {
    $provider = InsuranceProvider::factory()->create([
        'is_nhis' => false,
    ]);

    $response = $this->put(route('admin.insurance.providers.update', $provider), [
        'name' => $provider->name,
        'code' => $provider->code,
        'claim_submission_method' => 'online',
        'is_nhis' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('insurance_providers', [
        'id' => $provider->id,
        'is_nhis' => true,
    ]);
});

it('updates provider to unset is_nhis flag', function () {
    $provider = InsuranceProvider::factory()->nhis()->create();

    $response = $this->put(route('admin.insurance.providers.update', $provider), [
        'name' => $provider->name,
        'code' => $provider->code,
        'claim_submission_method' => 'online',
        'is_nhis' => false,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('insurance_providers', [
        'id' => $provider->id,
        'is_nhis' => false,
    ]);
});

it('returns is_nhis flag in provider show response', function () {
    $provider = InsuranceProvider::factory()->nhis()->create();

    $response = $this->get(route('admin.insurance.providers.show', $provider));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Providers/Show')
        ->where('provider.is_nhis', true)
    );
});

it('returns is_nhis flag in provider edit response', function () {
    $provider = InsuranceProvider::factory()->nhis()->create();

    $response = $this->get(route('admin.insurance.providers.edit', $provider));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Providers/Edit')
        ->where('provider.data.is_nhis', true)
    );
});

it('identifies NHIS provider correctly using isNhis method', function () {
    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $regularProvider = InsuranceProvider::factory()->create(['is_nhis' => false]);

    expect($nhisProvider->isNhis())->toBeTrue();
    expect($regularProvider->isNhis())->toBeFalse();
});
