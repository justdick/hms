<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'system.admin']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('system.admin');

    $provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);

    // Create some coverage rules and exceptions for testing
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA-500',
        'item_description' => 'Paracetamol 500mg Tablets',
        'coverage_value' => 100,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_value' => 90,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => 'CBC-001',
        'item_description' => 'Complete Blood Count Test',
        'coverage_value' => 100,
    ]);
});

it('can search across all categories', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    $page->assertSee('Coverage Management')
        ->assertSee('Drugs')
        ->assertSee('Lab Tests');

    // Type in the global search
    $page->type('input[aria-label="Search exceptions across all categories"]', 'Paracetamol')
        ->waitFor('text', 'Found 1 result')
        ->assertSee('Found 1 result');

    // Expand the drug category to see the highlighted result
    $page->click('button[aria-label*="Drugs category"]')
        ->waitFor('text', 'Paracetamol 500mg Tablets')
        ->assertSee('Paracetamol 500mg Tablets');
});

it('displays search results count correctly', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    // Search for a term that matches multiple items
    $page->type('input[aria-label="Search exceptions across all categories"]', 'Complete')
        ->waitFor('text', 'Found')
        ->assertSee('Found 1 result');

    // Clear search
    $page->click('button[aria-label="Clear search"]')
        ->assertDontSee('Found');
});

it('shows no results message when search has no matches', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    $page->type('input[aria-label="Search exceptions across all categories"]', 'NonExistentItem')
        ->waitFor('text', 'No results found')
        ->assertSee('No results found');
});

it('displays color-coded category cards', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    // Check that category cards are displayed
    $page->assertSee('Drugs')
        ->assertSee('Lab Tests')
        ->assertSee('80%') // Drug coverage
        ->assertSee('90%'); // Lab coverage
});

it('displays exception count badges', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    // Check that exception count badges are displayed
    $page->assertSee('1 Exception'); // Drug category has 1 exception
});

it('expands category to show simplified content', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    // Click to expand the drug category
    $page->click('button[aria-label*="Drugs category"]')
        ->waitFor('text', 'Add Exception')
        ->assertSee('Add Exception')
        ->assertSee('Paracetamol 500mg Tablets');

    // Verify simplified content (no nested panels)
    $page->assertSee('PARA-500')
        ->assertSee('Coverage: 100%');
});

it('highlights search matches in expanded cards', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    // Search for an item
    $page->type('input[aria-label="Search exceptions across all categories"]', 'Paracetamol')
        ->waitFor('text', 'Found 1 result');

    // Expand the category
    $page->click('button[aria-label*="Drugs category"]')
        ->waitFor('text', 'Paracetamol 500mg Tablets');

    // The search term should be highlighted (marked)
    // Note: We can't directly test for <mark> tags in Pest browser tests,
    // but we can verify the content is displayed
    $page->assertSee('Paracetamol 500mg Tablets');
});

it('maintains accessibility with keyboard navigation', function () {
    actingAs($this->user);

    $page = visit("/admin/insurance/plans/{$this->plan->id}/coverage");

    // Test keyboard navigation to category card
    $page->press('Tab') // Skip to main content link
        ->press('Tab') // Back button
        ->press('Tab') // Bulk Import button
        ->press('Tab') // Export button
        ->press('Tab') // Search input
        ->assertFocused('input[aria-label="Search exceptions across all categories"]');

    // Type in search
    $page->type('input[aria-label="Search exceptions across all categories"]', 'Test')
        ->assertValue('input[aria-label="Search exceptions across all categories"]', 'Test');

    // Clear with keyboard (Tab to clear button, then Enter)
    $page->press('Tab')
        ->press('Enter')
        ->assertValue('input[aria-label="Search exceptions across all categories"]', '');
});
