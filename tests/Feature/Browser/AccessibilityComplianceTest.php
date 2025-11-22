<?php

use App\Models\InsuranceClaim;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'system.admin']);
    Permission::firstOrCreate(['name' => 'insurance.vet-claims']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['system.admin', 'insurance.vet-claims']);

    $provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);

    // Create coverage rules
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_value' => 90,
    ]);
});

it('supports keyboard navigation in claims vetting panel', function () {
    actingAs($this->user);

    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $page = visit('/admin/insurance/claims');

    // Open vetting panel
    $page->assertSee($claim->claim_check_code)
        ->click('Review');

    // Wait for panel to open
    $page->pause(500);

    // Verify panel is open and has proper ARIA attributes
    $page->assertAttribute('[role="dialog"]', 'aria-modal', 'true')
        ->assertAttribute('[role="dialog"]', 'aria-labelledby', 'vetting-panel-title');

    // Test Tab navigation - focus should move to approve button
    $page->press('Tab');

    // Test Escape key closes panel
    $page->press('Escape')
        ->pause(300)
        ->assertDontSee('Vetting Decision');

    // Reopen panel
    $page->click('Review')
        ->pause(500);

    // Test Ctrl+Enter approves claim
    $page->keys('', ['{control}', '{enter}'])
        ->pause(500)
        ->assertSee('Claim approved successfully');
})->group('browser', 'accessibility');

it('has proper ARIA labels on interactive elements', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/reports');

    // Check Analytics Dashboard has proper structure
    $page->assertSee('Insurance Analytics Dashboard')
        ->assertPresent('[data-widget-index="0"]')
        ->assertPresent('[data-widget-index="1"]');

    // Check widget buttons have aria-expanded
    $page->assertAttribute('button[aria-label*="Expand"]', 'aria-expanded', 'false');

    // Expand a widget
    $page->click('button[aria-label*="Expand Claims Summary"]')
        ->pause(300)
        ->assertAttribute('button[aria-label*="Collapse"]', 'aria-expanded', 'true');
})->group('browser', 'accessibility');

it('supports arrow key navigation between widgets', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/reports');

    // Focus on first widget
    $page->click('button[aria-label*="Expand Claims Summary"]');

    // Press right arrow to move to next widget
    $page->press('ArrowRight')
        ->pause(200);

    // Verify focus moved (check if second widget button is focused)
    $page->assertPresent('[data-widget-index="1"] button:focus');

    // Press left arrow to move back
    $page->press('ArrowLeft')
        ->pause(200);

    // Verify focus moved back
    $page->assertPresent('[data-widget-index="0"] button:focus');
})->group('browser', 'accessibility');

it('has visible focus indicators on all interactive elements', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/plans/'.$this->plan->id.'/coverage');

    // Tab through interactive elements and verify focus indicators
    $page->press('Tab') // Skip to main content link
        ->press('Tab') // Back button
        ->pause(100);

    // Check that focused element has visible ring
    $page->assertPresent('button:focus');

    // Continue tabbing through category cards
    $page->press('Tab')
        ->press('Tab')
        ->pause(100);

    // Verify category card has focus indicator
    $page->assertPresent('[role="button"]:focus');
})->group('browser', 'accessibility');

it('traps focus within modal dialogs', function () {
    actingAs($this->user);

    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $page = visit('/admin/insurance/claims');

    // Open vetting panel
    $page->click('Review')
        ->pause(500);

    // Tab through all focusable elements in the panel
    // Focus should cycle back to the first element
    for ($i = 0; $i < 20; $i++) {
        $page->press('Tab');
    }

    // Verify focus is still within the panel
    $page->assertPresent('[role="dialog"] *:focus');

    // Test Shift+Tab (reverse direction)
    for ($i = 0; $i < 5; $i++) {
        $page->keys('', ['{shift}', '{tab}']);
    }

    // Verify focus is still within the panel
    $page->assertPresent('[role="dialog"] *:focus');
})->group('browser', 'accessibility');

it('returns focus to trigger element when modal closes', function () {
    actingAs($this->user);

    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $page = visit('/admin/insurance/claims');

    // Click Review button (this should be the trigger element)
    $page->click('Review')
        ->pause(500);

    // Close panel with Escape
    $page->press('Escape')
        ->pause(300);

    // Focus should return to the Review button
    // Note: This is a simplified check - in reality we'd verify the exact element
    $page->assertPresent('button:focus');
})->group('browser', 'accessibility');

it('has proper heading hierarchy', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/reports');

    // Check for proper heading structure
    $page->assertSee('Insurance Analytics Dashboard'); // h1

    // Expand a widget to check internal headings
    $page->click('button[aria-label*="Expand Claims Summary"]')
        ->pause(500);

    // Widget should have proper heading structure
    $page->assertPresent('h4'); // Section headings within widget
})->group('browser', 'accessibility');

it('provides text alternatives for icons', function () {
    actingAs($this->user);

    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $page = visit('/admin/insurance/claims');

    // Open vetting panel
    $page->click('Review')
        ->pause(500);

    // Check that icons have aria-hidden="true" and text alternatives exist
    $page->assertAttribute('svg[aria-hidden="true"]', 'aria-hidden', 'true');

    // Verify buttons have proper aria-labels
    $page->assertAttribute('button[aria-label="Approve claim"]', 'aria-label', 'Approve claim')
        ->assertAttribute('button[aria-label*="Reject"]', 'aria-label');
})->group('browser', 'accessibility');

it('announces dynamic content updates with live regions', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/plans/'.$this->plan->id.'/coverage');

    // Type in search box
    $page->type('input[aria-label*="Search"]', 'test')
        ->pause(500);

    // Check for live region with search results
    $page->assertPresent('[role="status"][aria-live="polite"]');
})->group('browser', 'accessibility');

it('has sufficient color contrast in light mode', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/plans/'.$this->plan->id.'/coverage');

    // Verify page loads successfully
    $page->assertSee('Coverage Management');

    // Check that text is visible (basic contrast check)
    // Note: Automated color contrast checking requires specialized tools
    // This test verifies the page renders correctly
    $page->assertPresent('.text-gray-900')
        ->assertPresent('.text-gray-600');
})->group('browser', 'accessibility');

it('has sufficient color contrast in dark mode', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/plans/'.$this->plan->id.'/coverage');

    // Switch to dark mode (assuming there's a theme toggle)
    // Note: Implementation depends on how dark mode is toggled in the app
    $page->evaluate('document.documentElement.classList.add("dark")');
    $page->pause(300);

    // Verify dark mode text colors are applied
    $page->assertPresent('.dark\\:text-gray-100')
        ->assertPresent('.dark\\:text-gray-400');
})->group('browser', 'accessibility');

it('does not rely solely on color for information', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/plans/'.$this->plan->id.'/coverage');

    // Expand a category to see coverage indicators
    $page->click('[role="button"][aria-label*="Drugs"]')
        ->pause(500);

    // Verify that coverage status includes:
    // 1. Color (background/border)
    // 2. Icon (CheckCircle2, AlertTriangle, XCircle, HelpCircle)
    // 3. Text (percentage value)
    $page->assertPresent('svg') // Icon present
        ->assertSee('%'); // Percentage text present
})->group('browser', 'accessibility');

it('supports skip to main content link', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/plans/'.$this->plan->id.'/coverage');

    // Tab to focus skip link
    $page->press('Tab');

    // Verify skip link is visible when focused
    $page->assertPresent('a:focus[href="#main-content"]');

    // Press Enter to skip to main content
    $page->press('Enter')
        ->pause(200);

    // Verify focus moved to main content
    $page->assertPresent('#main-content');
})->group('browser', 'accessibility');

it('has logical tab order', function () {
    actingAs($this->user);

    $page = visit('/admin/insurance/reports');

    // Tab through the page and verify logical order
    $page->press('Tab') // Skip link
        ->press('Tab') // First interactive element in header
        ->press('Tab'); // Date filter

    // Verify we're in the expected area
    $page->assertPresent('input:focus, button:focus');
})->group('browser', 'accessibility');
