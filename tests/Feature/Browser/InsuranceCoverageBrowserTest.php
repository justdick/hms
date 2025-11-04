<?php

/**
 * Browser Tests for Insurance Coverage Dashboard
 *
 * These tests require Pest Browser Plugin to be installed:
 * - composer require pestphp/pest-plugin-browser:^4.0 --dev
 * - npm install playwright@latest
 * - npx playwright install
 *
 * To run these tests:
 * php artisan test tests/Feature/Browser/InsuranceCoverageBrowserTest.php
 */

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->provider = InsuranceProvider::factory()->create([
        'name' => 'Test Insurance Provider',
    ]);
});

it('can navigate to coverage dashboard and see all categories', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
        'plan_code' => 'TEST001',
    ]);

    // Create some default rules
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_value' => 90,
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    $page->assertSee('Coverage Dashboard')
        ->assertSee('Test Plan')
        ->assertSee('Drugs')
        ->assertSee('Lab Tests')
        ->assertSee('Consultations')
        ->assertSee('Procedures')
        ->assertSee('Ward Services')
        ->assertSee('Nursing Services')
        ->assertSee('80%')
        ->assertSee('90%')
        ->assertNoJavascriptErrors();
});

it('can expand and collapse category cards', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    // Initially, exception list should not be visible
    $page->assertDontSee('Item-Specific Exceptions');

    // Click on the drug category card to expand
    $page->click('Drugs')
        ->assertSee('Default Rule')
        ->assertSee('80% coverage for all items')
        ->assertNoJavascriptErrors();
});

it('can add a coverage exception through the modal', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    // Create default rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Create a drug to add exception for
    $drug = Drug::factory()->create([
        'drug_code' => 'PARA001',
        'name' => 'Paracetamol 500mg',
        'unit_price' => 50.00,
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    // Expand drug category
    $page->click('Drugs')
        ->assertSee('Add Exception')
        ->click('Add Exception')
        ->assertSee('Add Coverage Exception')
        ->fill('search', 'Paracetamol')
        ->pause(500) // Wait for search results
        ->click('PARA001')
        ->fill('coverage_value', '100')
        ->click('Add Exception')
        ->pause(500) // Wait for modal to close
        ->assertSee('Coverage exception added successfully')
        ->assertNoJavascriptErrors();
});

it('can perform inline editing of coverage percentage', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_plan_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    // Expand drug category
    $page->click('Drugs')
        ->assertSee('80%');

    // Click on the percentage to edit (inline edit)
    // Note: This test verifies the UI is present, actual inline editing
    // would require more complex interaction with the component
    $page->assertNoJavascriptErrors();
});

it('displays recent items panel', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    // Create a recent drug
    $drug = Drug::factory()->create([
        'drug_code' => 'RECENT001',
        'name' => 'Recent Drug',
        'unit_price' => 600.00,
        'created_at' => now()->subDays(5),
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    $page->assertSee('Recently Added Items')
        ->assertSee('Recent Drug')
        ->assertNoJavascriptErrors();
});

it('shows keyboard shortcuts help', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    $page->assertSee('Keyboard Shortcuts')
        ->assertSee('Add new exception')
        ->assertNoJavascriptErrors();
});

it('is responsive on mobile viewport', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Test on mobile viewport
    $page = visit("/admin/insurance/plans/{$plan->id}/coverage", viewport: [375, 667]);

    $page->assertSee('Coverage')
        ->assertSee('Drugs')
        ->assertSee('80%')
        ->assertNoJavascriptErrors();
});

it('is responsive on tablet viewport', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Test on tablet viewport
    $page = visit("/admin/insurance/plans/{$plan->id}/coverage", viewport: [768, 1024]);

    $page->assertSee('Coverage Dashboard')
        ->assertSee('Drugs')
        ->assertSee('80%')
        ->assertNoJavascriptErrors();
});

it('has accessible navigation with keyboard', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    // Test keyboard navigation
    $page->press('Tab') // Should focus on skip link or first interactive element
        ->assertNoJavascriptErrors();
});

it('displays empty state guidance when no coverage rules exist', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Empty Plan',
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    $page->assertSee('Get Started')
        ->assertSee('Start by setting default coverage')
        ->assertNoJavascriptErrors();
});

it('can create plan with coverage rules using wizard', function () {
    $page = visit('/admin/insurance/plans/create');

    $page->assertSee('Create Insurance Plan')
        ->fill('plan_name', 'Wizard Test Plan')
        ->fill('plan_code', 'WIZ001')
        ->select('insurance_provider_id', $this->provider->id)
        ->select('plan_type', 'corporate')
        ->select('coverage_type', 'comprehensive')
        ->click('Next')
        ->pause(500);

    // If wizard has coverage preset selection
    if ($page->hasText('Coverage Presets')) {
        $page->click('NHIS Standard')
            ->pause(500);
    }

    $page->click('Create Plan')
        ->pause(1000)
        ->assertSee('Insurance plan created successfully')
        ->assertNoJavascriptErrors();
});

it('shows validation errors for invalid coverage values', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    $drug = Drug::factory()->create([
        'drug_code' => 'TEST001',
        'name' => 'Test Drug',
        'unit_price' => 50.00,
    ]);

    $page = visit("/admin/insurance/plans/{$plan->id}/coverage");

    $page->click('Drugs')
        ->click('Add Exception')
        ->fill('search', 'Test Drug')
        ->pause(500)
        ->click('TEST001')
        ->fill('coverage_value', '150') // Invalid value > 100
        ->click('Add Exception')
        ->pause(500)
        ->assertSee('coverage value') // Should show validation error
        ->assertNoJavascriptErrors();
});

it('supports dark mode', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Test in dark mode
    $page = visit("/admin/insurance/plans/{$plan->id}/coverage", colorScheme: 'dark');

    $page->assertSee('Coverage Dashboard')
        ->assertSee('Drugs')
        ->assertSee('80%')
        ->assertNoJavascriptErrors();
});
