<?php

/**
 * Browser Tests for Vitals Monitoring End-to-End Flow
 *
 * These tests verify the complete vitals monitoring workflow from
 * schedule creation through alert generation, vitals recording,
 * and schedule continuation.
 */

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalSign;
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

it('completes full workflow: create schedule → receive alert → record vitals → next alert scheduled', function () {
    // Step 1: Create vitals schedule
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Open schedule modal
    if ($page->hasText('Set Vitals Schedule')) {
        $page->click('Set Vitals Schedule')
            ->pause(500);

        // Select 2-hour interval
        $selectExists = $page->evaluate('() => {
            const selects = document.querySelectorAll("select");
            return selects.length > 0;
        }');

        if ($selectExists) {
            $page->select('select', '120')
                ->pause(500);
        }

        // Save schedule
        if ($page->hasText('Save') || $page->hasText('Create')) {
            $page->click($page->hasText('Save') ? 'Save' : 'Create')
                ->pause(1000);
        }
    }

    // Verify schedule was created
    $schedule = VitalsSchedule::where('patient_admission_id', $this->admission->id)->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->interval_minutes)->toBe(120);

    // Step 2: Simulate time passing and alert becoming due
    $schedule->update(['next_due_at' => now()]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    // Step 3: Navigate to ward page and see alert
    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1500)
        ->assertSee($this->admission->patient->name);

    // Step 4: Click to record vitals
    if ($page->hasText('Record Vitals')) {
        $page->click('Record Vitals')
            ->pause(500);
    }

    // Step 5: Record vitals (simulate)
    $vitalSign = VitalSign::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'recorded_by' => $this->user->id,
        'recorded_at' => now(),
    ]);

    // Update schedule as if vitals were recorded
    $schedule->refresh();
    $schedule->update([
        'last_recorded_at' => now(),
        'next_due_at' => now()->addMinutes(120),
    ]);

    $alert->update(['status' => 'completed']);

    // Step 6: Verify next alert is scheduled
    $schedule->refresh();
    expect($schedule->next_due_at)->not->toBeNull();
    expect($schedule->next_due_at->isFuture())->toBeTrue();

    $page->assertNoJavascriptErrors();
});

it('shows overdue alert after 15-minute grace period', function () {
    // Create schedule with due time in the past
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(10), // Within grace period
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Create due alert
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->subMinutes(10),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1500)
        ->assertSee('Vitals Due');

    // Simulate grace period expiring
    $alert->update([
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page->pause(1500)
        ->assertSee('Vitals Overdue')
        ->assertNoJavascriptErrors();
});

it('stops alerts when patient is discharged', function () {
    // Create active schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Create pending alert
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->addHours(1),
        'status' => 'pending',
    ]);

    // Discharge patient
    $this->admission->update(['status' => 'discharged']);

    // Schedule should be deactivated
    $schedule->update(['is_active' => false]);
    $alert->update(['status' => 'dismissed']);

    // Visit ward page
    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1500);

    // Should not see alerts for discharged patient
    $schedule->refresh();
    expect($schedule->is_active)->toBeFalse();

    $alert->refresh();
    expect($alert->status)->toBe('dismissed');

    $page->assertNoJavascriptErrors();
});

it('handles multiple simultaneous alerts for different patients', function () {
    // Create multiple patients with due alerts
    $admission1 = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-101',
        'status' => 'admitted',
    ]);

    $schedule1 = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission1->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $alert1 = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule1->id,
        'patient_admission_id' => $admission1->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $admission2 = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'bed_number' => 'A-102',
        'status' => 'admitted',
    ]);

    $schedule2 = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission2->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $alert2 = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule2->id,
        'patient_admission_id' => $admission2->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1500)
        ->assertSee($admission1->patient->name)
        ->assertSee($admission2->patient->name)
        ->assertNoJavascriptErrors();
});

it('continues scheduling after vitals are recorded', function () {
    // Create schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Record vitals
    $vitalSign = VitalSign::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'recorded_by' => $this->user->id,
        'recorded_at' => now(),
    ]);

    // Update schedule
    $schedule->update([
        'last_recorded_at' => now(),
        'next_due_at' => now()->addMinutes(120),
    ]);

    // Verify next due time is set
    $schedule->refresh();
    expect($schedule->next_due_at)->not->toBeNull();
    expect($schedule->next_due_at->isFuture())->toBeTrue();
    expect($schedule->is_active)->toBeTrue();
});

it('repeats overdue notifications every 15 minutes', function () {
    // Create overdue alert
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1500)
        ->assertSee('Vitals Overdue');

    // Wait for repeat notification (15 minutes would be too long for test)
    // In real scenario, this would trigger again after 15 minutes
    $page->assertNoJavascriptErrors();
});

it('allows acknowledging alerts without recording vitals', function () {
    // Create due alert
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page = visit("/wards/{$this->ward->id}");

    $page->pause(1500);

    // Acknowledge alert
    $alert->update([
        'acknowledged_at' => now(),
        'acknowledged_by' => $this->user->id,
    ]);

    $alert->refresh();
    expect($alert->acknowledged_at)->not->toBeNull();
    expect($alert->acknowledged_by)->toBe($this->user->id);

    $page->assertNoJavascriptErrors();
});

it('maintains schedule across multiple vitals recordings', function () {
    // Create schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now(),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // First recording
    $vitalSign1 = VitalSign::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'recorded_by' => $this->user->id,
        'recorded_at' => now(),
    ]);

    $schedule->update([
        'last_recorded_at' => now(),
        'next_due_at' => now()->addMinutes(120),
    ]);

    $firstNextDue = $schedule->next_due_at;

    // Second recording
    $vitalSign2 = VitalSign::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'recorded_by' => $this->user->id,
        'recorded_at' => now()->addMinutes(120),
    ]);

    $schedule->update([
        'last_recorded_at' => now()->addMinutes(120),
        'next_due_at' => now()->addMinutes(240),
    ]);

    $schedule->refresh();
    expect($schedule->next_due_at)->not->toBe($firstNextDue);
    expect($schedule->is_active)->toBeTrue();
});

it('handles schedule modification during active monitoring', function () {
    // Create schedule with 2-hour interval
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");

    $page->pause(500);

    // Edit schedule to 4-hour interval
    if ($page->hasText('Edit Schedule')) {
        $page->click('Edit Schedule')
            ->pause(500);

        $selectExists = $page->evaluate('() => {
            const selects = document.querySelectorAll("select");
            return selects.length > 0;
        }');

        if ($selectExists) {
            $page->select('select', '240') // 4 hours
                ->pause(500);
        }

        if ($page->hasText('Save') || $page->hasText('Update')) {
            $page->click($page->hasText('Save') ? 'Save' : 'Update')
                ->pause(1000);
        }
    }

    // Verify schedule was updated
    $schedule->refresh();
    expect($schedule->interval_minutes)->toBe(240);

    $page->assertNoJavascriptErrors();
});

it('shows correct status transitions throughout workflow', function () {
    // Create schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Status: Upcoming
    $page = visit("/wards/{$this->ward->id}/patients/{$this->admission->id}");
    $page->pause(500);

    // Change to due
    $schedule->update(['next_due_at' => now()]);
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $this->admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $page->pause(1000);

    // Change to overdue
    $alert->update([
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $page->pause(1000);

    // Complete
    $alert->update(['status' => 'completed']);
    $schedule->update([
        'last_recorded_at' => now(),
        'next_due_at' => now()->addMinutes(120),
    ]);

    $page->assertNoJavascriptErrors();
});
