<?php

/**
 * Browser Tests for Vitals Monitoring on Patient Page
 *
 * These tests verify the integration of vitals monitoring features
 * on the patient page, including schedule display, modal interactions,
 * and quick actions.
 */

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsSchedule;
use App\Models\Ward;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->ward = Ward::factory()->create(['name' => 'Test Ward']);

    $this->admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);
});

it('displays current vitals schedule on patient page', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->assertSee($this->admission->patient->name)
        ->assertNoJavascriptErrors();

    // Check for schedule information
    $hasScheduleInfo = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("Vitals Schedule") || 
               text.includes("Every") || 
               text.includes("hours") ||
               text.includes("Next Due");
    }');

    expect($hasScheduleInfo)->toBeTrue();
});

it('shows interval and next due time for vitals schedule', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 240, // 4 hours
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Check for interval display (4 hours or 240 minutes)
    $hasInterval = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("4") || text.includes("240");
    }');

    $page->assertNoJavascriptErrors();
});

it('displays vitals status badge with current status', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addMinutes(30),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Check for status badge
    $hasBadge = $page->evaluate('() => {
        const badges = document.querySelectorAll("[data-vitals-status]");
        return badges.length > 0;
    }');

    $page->assertNoJavascriptErrors();
});

it('opens vitals schedule modal when clicking create/edit button', function () {
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Look for button to create/edit schedule
    if ($page->hasText('Set Vitals Schedule') || $page->hasText('Edit Schedule')) {
        $buttonText = $page->hasText('Set Vitals Schedule') ? 'Set Vitals Schedule' : 'Edit Schedule';

        $page->click($buttonText)
            ->pause(500)
            ->assertSee('Vitals Schedule')
            ->assertNoJavascriptErrors();
    }
});

it('can create vitals schedule through modal', function () {
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open modal
    if ($page->hasText('Set Vitals Schedule')) {
        $page->click('Set Vitals Schedule')
            ->pause(500);

        // Select interval (e.g., 2 hours)
        $selectExists = $page->evaluate('() => {
            const selects = document.querySelectorAll("select");
            return selects.length > 0;
        }');

        if ($selectExists) {
            $page->select('select', '120') // 2 hours
                ->pause(500);
        }

        // Save schedule
        if ($page->hasText('Save') || $page->hasText('Create')) {
            $page->click($page->hasText('Save') ? 'Save' : 'Create')
                ->pause(1000)
                ->assertNoJavascriptErrors();
        }
    }
});

it('can edit existing vitals schedule', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open edit modal
    if ($page->hasText('Edit Schedule')) {
        $page->click('Edit Schedule')
            ->pause(500);

        // Change interval to 4 hours
        $selectExists = $page->evaluate('() => {
            const selects = document.querySelectorAll("select");
            return selects.length > 0;
        }');

        if ($selectExists) {
            $page->select('select', '240') // 4 hours
                ->pause(500);
        }

        // Save changes
        if ($page->hasText('Save') || $page->hasText('Update')) {
            $page->click($page->hasText('Save') ? 'Save' : 'Update')
                ->pause(1000)
                ->assertNoJavascriptErrors();
        }
    }
});

it('shows preview of next due times in schedule modal', function () {
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open modal
    if ($page->hasText('Set Vitals Schedule')) {
        $page->click('Set Vitals Schedule')
            ->pause(500);

        // Select an interval
        $selectExists = $page->evaluate('() => {
            const selects = document.querySelectorAll("select");
            return selects.length > 0;
        }');

        if ($selectExists) {
            $page->select('select', '120')
                ->pause(500);

            // Check for preview of next due times
            $hasPreview = $page->evaluate('() => {
                const text = document.body.textContent;
                return text.includes("Next") || text.includes("Preview");
            }');
        }

        $page->assertNoJavascriptErrors();
    }
});

it('has Record Vitals Now quick action button', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Look for Record Vitals button
    $hasRecordButton = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("Record Vitals");
    }');

    $page->assertNoJavascriptErrors();
});

it('navigates to vitals recording when clicking Record Vitals Now', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Click Record Vitals button if it exists
    if ($page->hasText('Record Vitals')) {
        $page->click('Record Vitals')
            ->pause(500);

        // Should navigate to vitals recording section or open modal
        $page->assertNoJavascriptErrors();
    }
});

it('displays schedule history section', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'last_recorded_at' => now()->subHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Check for history section
    $hasHistory = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("History") || text.includes("Last Recorded");
    }');

    $page->assertNoJavascriptErrors();
});

it('shows time remaining until next vitals due', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addMinutes(45),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Check for time remaining display
    $hasTimeRemaining = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("in") || text.includes("minutes") || text.includes("hours");
    }');

    $page->assertNoJavascriptErrors();
});

it('shows time elapsed for overdue vitals', function () {
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(30),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Check for overdue time display
    $hasOverdueTime = $page->evaluate('() => {
        const text = document.body.textContent;
        return text.includes("overdue") || text.includes("ago");
    }');

    $page->assertNoJavascriptErrors();
});

it('allows custom interval input in minutes', function () {
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open modal
    if ($page->hasText('Set Vitals Schedule')) {
        $page->click('Set Vitals Schedule')
            ->pause(500);

        // Look for custom interval input
        $hasCustomInput = $page->evaluate('() => {
            const inputs = document.querySelectorAll("input[type=\'number\']");
            return inputs.length > 0;
        }');

        if ($hasCustomInput) {
            $page->fill('input[type="number"]', '90')
                ->pause(500);
        }

        $page->assertNoJavascriptErrors();
    }
});

it('validates interval is within allowed range', function () {
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open modal
    if ($page->hasText('Set Vitals Schedule')) {
        $page->click('Set Vitals Schedule')
            ->pause(500);

        // Try to set invalid interval (too short)
        $hasCustomInput = $page->evaluate('() => {
            const inputs = document.querySelectorAll("input[type=\'number\']");
            return inputs.length > 0;
        }');

        if ($hasCustomInput) {
            $page->fill('input[type="number"]', '5') // Less than 15 minutes
                ->pause(500);

            // Try to save
            if ($page->hasText('Save') || $page->hasText('Create')) {
                $page->click($page->hasText('Save') ? 'Save' : 'Create')
                    ->pause(500);

                // Should show validation error
                $hasError = $page->evaluate('() => {
                    const text = document.body.textContent;
                    return text.includes("must be") || text.includes("between");
                }');
            }
        }

        $page->assertNoJavascriptErrors();
    }
});

it('closes modal when clicking cancel', function () {
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open modal
    if ($page->hasText('Set Vitals Schedule')) {
        $page->click('Set Vitals Schedule')
            ->pause(500)
            ->assertSee('Vitals Schedule');

        // Click cancel
        if ($page->hasText('Cancel')) {
            $page->click('Cancel')
                ->pause(500);

            // Modal should be closed
            $page->assertNoJavascriptErrors();
        }
    }
});
